<?php

namespace App\Http\Controllers;

use App\Jobs\RunViabilityCheck;
use App\Models\ViabilityReport;
use App\Payments\PaymentGateway;
use App\Simulation\Data\DayPart;
use App\Simulation\Data\ParameterSheet;
use App\Simulation\Sale\BuyingCosts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** The standalone viability check (SPEC §11): no game account needed. */
class ViabilityController extends Controller
{
    public function create(): Response
    {
        $market = config('market.'.config('viability.market'));

        return Inertia::render('viability/create', [
            'map' => [
                'tile_url' => config('map.tile_url'),
                'attribution' => config('map.attribution'),
                'max_zoom' => config('map.max_zoom'),
                'centre' => config('map.centre'),
            ],
            'day_parts' => array_map(fn (DayPart $p) => [
                'value' => $p->value,
                'start_hour' => $market['day_parts'][$p->value]['start_hour'],
                'end_hour' => $market['day_parts'][$p->value]['end_hour'],
            ], DayPart::cases()),
            'licence_day_parts' => $market['licence_day_parts'],
            'purchase' => [
                'held_months' => $market['purchase']['deposit_months_of_rent'] + $market['purchase']['guarantee_months_of_rent'],
                'legal_base_cents' => $market['purchase']['legal_fees']['base_cents'],
                'legal_share' => $market['purchase']['legal_fees']['share_of_traspaso'],
                'licence_cents' => array_sum(array_filter($market['purchase']['licence_change'], 'is_int')),
            ],
            'staff_max' => $market['decision_limits']['staff_count']['max'],
            'runs' => (int) config('viability.runs'),
            'years' => (int) config('viability.years'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $market = config('market.'.config('viability.market'));
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'traspaso_euros' => ['required', 'integer', 'min:0', 'max:2000000'],
            'rent_euros' => ['required', 'integer', 'min:100', 'max:50000'],
            'floor_area_m2' => ['required', 'integer', 'min:10', 'max:1000'],
            'indoor_seats' => ['required', 'integer', 'min:0', 'max:300'],
            'terrace_seats' => ['required', 'integer', 'min:0', 'max:200'],
            'licence' => ['required', Rule::in(['cafe', 'cafe_bar'])],
            'kitchen' => ['required', Rule::in(['none', 'basic', 'full'])],
            'condition' => ['required', 'integer', 'between:1,10'],
            'capital_euros' => ['required', 'integer', 'min:1000', 'max:5000000'],
            'open_day_parts' => ['required', 'array', 'min:1'],
            'open_day_parts.*' => [Rule::in(array_map(fn (DayPart $p) => $p->value, DayPart::cases()))],
            'staff_count' => ['required', 'integer', 'min:0', 'max:'.$market['decision_limits']['staff_count']['max']],
            'quality_tier' => ['required', Rule::in(['budget', 'standard', 'premium'])],
        ]);

        $needed = (new BuyingCosts(new ParameterSheet($market)))->cashNeededCents($data['traspaso_euros'] * 100, $data['rent_euros'] * 100);

        if ($needed > $data['capital_euros'] * 100) {
            throw ValidationException::withMessages(['capital_euros' => 'Your money must cover the traspaso, the landlord\'s deposit and guarantee, and the buying fees: '.number_format(intdiv($needed + 99, 100), 0, ',', '.').' € here.']);
        }

        $notAllowed = array_diff($data['open_day_parts'], $market['licence_day_parts'][$data['licence']]);

        if ($notAllowed !== []) {
            throw ValidationException::withMessages(['open_day_parts' => 'That licence doesn\'t allow opening at night.']);
        }

        if (RunViabilityCheck::nearestPoint((float) $data['lat'], (float) $data['lng']) === null) {
            throw ValidationException::withMessages(['lat' => 'There\'s no shopping street right there. Put the pin on the street where the café is.']);
        }

        $report = ViabilityReport::query()->create([
            'user_id' => $request->user()?->id,
            'inputs' => [
                'lat' => (float) $data['lat'],
                'lng' => (float) $data['lng'],
                'traspaso_cents' => $data['traspaso_euros'] * 100,
                'rent_month_cents' => $data['rent_euros'] * 100,
                'floor_area_m2' => $data['floor_area_m2'],
                'indoor_seats' => $data['indoor_seats'],
                'terrace_seats' => $data['terrace_seats'],
                'licence' => $data['licence'],
                'kitchen' => $data['kitchen'],
                'condition' => $data['condition'],
                'capital_cents' => $data['capital_euros'] * 100,
                'open_day_parts' => array_values(array_filter(
                    array_map(fn (DayPart $p) => $p->value, DayPart::cases()),
                    fn (string $p) => in_array($p, $data['open_day_parts'], true),
                )),
                'staff_count' => $data['staff_count'],
                'quality_tier' => $data['quality_tier'],
                'price_level' => 1.0,
            ],
        ]);

        RunViabilityCheck::dispatch($report->id);

        return to_route('viability.show', $report);
    }

    public function show(ViabilityReport $report): Response
    {
        $results = $report->results;
        $unlocked = $report->unlocked();

        return Inertia::render('viability/show', [
            'report' => [
                'uuid' => $report->uuid,
                'status' => $report->status,
                'inputs' => $report->inputs,
                'unlocked' => $unlocked,
                'created_at' => $report->created_at->toIso8601String(),
            ],
            'map' => ['tile_url' => config('map.tile_url'), 'attribution' => config('map.attribution')],
            // The free preview: the spot and the first year. The rest once paid.
            'preview' => $results === null ? null : [
                'runs' => $results['runs'],
                'years' => $results['years'],
                'spot' => $results['spot'],
                'open_year_1' => $results['open'][1] ?? $results['open']['1'],
            ],
            'full' => $results !== null && $unlocked ? $results : null,
            'price_cents' => config('payments.products.viability_report.price_cents'),
            'payments_enabled' => app(PaymentGateway::class)->configured(),
        ]);
    }
}
