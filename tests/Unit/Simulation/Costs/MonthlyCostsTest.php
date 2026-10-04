<?php

use App\Simulation\Costs\MonthlyCosts;
use App\Simulation\Data\DayPart;
use App\Simulation\Data\ParameterSheet;
use App\Simulation\Data\QualityTier;
use App\Simulation\Demand\SeasonalFactors;
use App\Simulation\Demand\Staffing;
use Tests\Support\SimulationFixtures;

function monthlyCosts(): MonthlyCosts
{
    return new MonthlyCosts(SimulationFixtures::sheet());
}

function costsFor(int $revenueCents, array $decisions = [], array $state = [])
{
    return monthlyCosts()->calculate(
        SimulationFixtures::state()->with(...$state),
        SimulationFixtures::decisions()->with(...$decisions),
        new SeasonalFactors(1.0, 30, 26, 0.5),
        $revenueCents,
    );
}

it('takes COGS as a share of revenue by quality tier', function (QualityTier $tier) {
    $share = SimulationFixtures::parameters()['cogs']['share_of_revenue'][$tier->value];

    expect(costsFor(1_000_000, ['qualityTier' => $tier])->cogsCents)->toBe((int) round(1_000_000 * $share));
})->with(QualityTier::cases());

it('costs staff at 14 payments a year plus social security', function () {
    $staff = SimulationFixtures::parameters()['staff'];
    $perPerson = $staff['gross_per_payment_cents'] * 14 / 12 * (1 + $staff['employer_social_security_rate']);

    expect(monthlyCosts()->staff(0))->toBe(0)
        ->and(monthlyCosts()->staff(3))->toBe((int) round(3 * $perPerson))
        ->and(costsFor(0, ['staffCount' => 2])->staffCents)->toBe(monthlyCosts()->staff(2));
});

it('charges the rent and the marketing spend as they are', function () {
    $costs = costsFor(500_000, ['marketingSpendCents' => 12_345]);

    expect($costs->rentCents)->toBe(SimulationFixtures::profile()->rentMonthCents)
        ->and($costs->marketingCents)->toBe(12_345);
});

it('scales utilities with the hours open', function () {
    $short = costsFor(0, ['openDayParts' => [DayPart::Morning]])->utilitiesCents;
    $long = costsFor(0, ['openDayParts' => [DayPart::Morning, DayPart::Lunch, DayPart::Afternoon, DayPart::Evening]])->utilitiesCents;

    expect($long)->toBeGreaterThan($short)
        ->and($short)->toBeGreaterThan(SimulationFixtures::parameters()['utilities']['base_month_cents']);
});

it('pays part-time cover for open hours nobody else covers, at the hourly staff cost', function () {
    $parameters = SimulationFixtures::parameters();
    $long = ['staffCount' => 0, 'openDayParts' => DayPart::cases(), 'openDaysPerWeek' => 7];
    $coverHours = Staffing::for(SimulationFixtures::decisions()->with(...$long), SimulationFixtures::sheet())->coverHoursPerWeek;
    $hourly = monthlyCosts()->staff(1) / ($parameters['staff']['full_time_hours_per_week'] * 52 / 12);

    expect($coverHours)->toBeGreaterThan(0.0)
        ->and(costsFor(0, $long)->staffCents)->toEqualWithDelta($coverHours * 52 / 12 * $hourly, 1)
        ->and(costsFor(0, ['staffCount' => 1, 'openDayParts' => [DayPart::Morning]])->staffCents)->toBe(monthlyCosts()->staff(1));
});

it('makes a long day cost wages, not just utilities', function () {
    $day = ['staffCount' => 1, 'openDayParts' => [DayPart::Morning, DayPart::Lunch, DayPart::Afternoon], 'openDaysPerWeek' => 6];
    $longer = [...$day, 'openDayParts' => [DayPart::Morning, DayPart::Lunch, DayPart::Afternoon, DayPart::Evening, DayPart::Night]];

    expect(costsFor(0, $longer)->staffCents)->toBeGreaterThan(costsFor(0, $day)->staffCents);
});

it('picks the cuota de autónomo band by income', function () {
    $bands = SimulationFixtures::parameters()['cuota_autonomo']['bands'];

    expect(monthlyCosts()->cuotaAutonomo(-500_000))->toBe($bands[0]['cuota_cents'])
        ->and(monthlyCosts()->cuotaAutonomo($bands[0]['max_income_cents']))->toBe($bands[0]['cuota_cents'])
        ->and(monthlyCosts()->cuotaAutonomo($bands[0]['max_income_cents'] + 1))->toBe($bands[1]['cuota_cents'])
        ->and(monthlyCosts()->cuotaAutonomo(100_000_000))->toBe(end($bands)['cuota_cents']);
});

it('only taxes a profit', function () {
    expect(costsFor(0)->taxesCents)->toBe(0)
        ->and(costsFor(3_000_000)->taxesCents)->toBeGreaterThan(0);
});

it('taxes profit after costs and the cuota at the configured rate', function () {
    $costs = costsFor(3_000_000);
    $beforeTax = 3_000_000 - ($costs->totalCents() - $costs->taxesCents);

    expect($costs->taxesCents)->toEqualWithDelta($beforeTax * SimulationFixtures::parameters()['income_tax']['rate'], 1);
});

it('charges more maintenance for older equipment and a terrace fee for terrace tables', function () {
    $profile = SimulationFixtures::profile();

    expect(costsFor(0, state: ['equipmentAgeMonths' => 180])->otherCents)
        ->toBeGreaterThan(costsFor(0, state: ['equipmentAgeMonths' => 12])->otherCents)
        ->and(costsFor(0, state: ['profile' => $profile->with(terraceSeats: 24)])->otherCents)
        ->toBeGreaterThan(costsFor(0, state: ['profile' => $profile->with(terraceSeats: 0)])->otherCents);
});

it('rejects a cuota table without an open-ended last band', function () {
    $parameters = SimulationFixtures::parameters();
    $parameters['cuota_autonomo']['bands'] = [['max_income_cents' => 100, 'cuota_cents' => 1]];

    (new MonthlyCosts(new ParameterSheet($parameters)))->cuotaAutonomo(500);
})->throws(InvalidArgumentException::class);
