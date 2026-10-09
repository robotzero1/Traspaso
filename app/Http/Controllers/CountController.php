<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\PedestrianCount;
use App\Simulation\Data\DayPart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** The phone page for counting pedestrians at a spot (SPEC §8, milestone 26). */
class CountController extends Controller
{
    public function index(Request $request): Response
    {
        $dayParts = config('market.zaragoza_cafe.day_parts');

        return Inertia::render('counts/index', [
            'counts' => $request->user()->pedestrianCounts()->latest('id')->get()
                ->map(fn (PedestrianCount $c) => [...$c->only(['id', 'lat', 'lng', 'day_part', 'minutes', 'count', 'note']), 'counted_on' => $c->counted_on->toDateString()])
                ->all(),
            'day_parts' => array_map(fn (DayPart $p) => [
                'value' => $p->value,
                'start_hour' => $dayParts[$p->value]['start_hour'],
                'end_hour' => $dayParts[$p->value]['end_hour'],
            ], DayPart::cases()),
            'map' => [
                'tile_url' => config('map.tile_url'),
                'attribution' => config('map.attribution'),
                'max_zoom' => config('map.max_zoom'),
                'centre' => config('map.centre'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            // Around Zaragoza.
            'lat' => ['required', 'numeric', 'between:41.55,41.75'],
            'lng' => ['required', 'numeric', 'between:-1.05,-0.75'],
            'day_part' => ['required', Rule::in(array_map(fn (DayPart $p) => $p->value, DayPart::cases()))],
            'minutes' => ['required', 'integer', 'min:1', 'max:60'],
            'count' => ['required', 'integer', 'min:0', 'max:10000'],
            'note' => ['nullable', 'string', 'max:80'],
        ]);

        $request->user()->pedestrianCounts()->create([
            ...$data,
            'lat' => round($data['lat'], 6),
            'lng' => round($data['lng'], 6),
            'counted_on' => Game::today()->toString(),
        ]);

        return to_route('counts.index');
    }

    public function destroy(Request $request, PedestrianCount $count): RedirectResponse
    {
        abort_unless($count->user_id === $request->user()->id, 403);
        $count->delete();

        return to_route('counts.index');
    }
}
