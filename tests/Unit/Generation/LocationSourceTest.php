<?php

use App\Generation\BusinessGenerator;
use App\Generation\CommercialPoints;
use App\Generation\Location;
use App\Simulation\Data\DayPart;
use App\Simulation\Demand\PotentialCustomers;
use App\Simulation\Demand\SeasonalFactors;
use App\Simulation\Rng\SeededRng;
use Tests\Support\SimulationFixtures;

function location(int $id, float $footfall = 6.0, string $street = 'main_street'): Location
{
    $byPart = array_fill_keys(array_map(fn (DayPart $p) => $p->value, DayPart::cases()), $footfall);

    return new Location(41.65 + $id / 10_000, -0.88, $footfall, $byPart, $street, $id);
}

it('hands out each point once, then nothing', function () {
    $neighbourhood = SimulationFixtures::neighbourhood();
    $points = new CommercialPoints([$neighbourhood->name => [location(1), location(2), location(3)]]);
    $rng = new SeededRng(1);

    $drawn = [$points->draw($neighbourhood, $rng), $points->draw($neighbourhood, $rng), $points->draw($neighbourhood, $rng)];

    expect(collect($drawn)->pluck('pointId')->sort()->values()->all())->toBe([1, 2, 3])
        ->and($points->draw($neighbourhood, $rng))->toBeNull()
        ->and($points->draw($neighbourhood->with(name: 'Elsewhere'), $rng))->toBeNull();
});

it('draws the same points for the same seed', function () {
    $neighbourhood = SimulationFixtures::neighbourhood();
    $draw = function () use ($neighbourhood) {
        $points = new CommercialPoints([$neighbourhood->name => array_map(fn ($i) => location($i), range(1, 10))]);
        $rng = new SeededRng(4);

        return array_map(fn () => $points->draw($neighbourhood, $rng)->pointId, range(1, 5));
    };

    expect($draw())->toBe($draw());
});

it('places generated businesses on real points with their footfall', function () {
    $neighbourhoods = SimulationFixtures::neighbourhoods();
    $points = [];

    foreach ($neighbourhoods as $n) {
        $points[$n->name] = array_map(fn ($i) => location(crc32($n->name) % 1000 * 100 + $i, 7.5, 'square'), range(1, 300));
    }

    $market = (new BusinessGenerator(SimulationFixtures::parameters()))
        ->generate(new SeededRng(1), $neighbourhoods, 80, new CommercialPoints($points));

    foreach ($market as $business) {
        expect($business->location)->not->toBeNull()
            ->and($business->profile->footfall)->toBe(7.5)
            ->and($business->profile->footfallByDayPart)->toHaveCount(5)
            ->and($business->streetType)->toBe('square');
    }

    expect(collect($market)->pluck('location.pointId')->unique())->toHaveCount(80);
});

it('falls back to generated footfall when a neighbourhood has no points', function () {
    $market = (new BusinessGenerator(SimulationFixtures::parameters()))
        ->generate(new SeededRng(1), SimulationFixtures::neighbourhoods(), 30, new CommercialPoints([]));

    foreach ($market as $business) {
        expect($business->location)->toBeNull()
            ->and($business->profile->footfallByDayPart)->toBe([]);
    }
});

it('generates the same market without a location source as before', function () {
    $generator = new BusinessGenerator(SimulationFixtures::parameters());

    expect(serialize($generator->generate(new SeededRng(9), SimulationFixtures::neighbourhoods(), 40)))
        ->toBe(serialize($generator->generate(new SeededRng(9), SimulationFixtures::neighbourhoods(), 40, null)));
});

it('uses the footfall of each day part when the business has one', function () {
    $potential = new PotentialCustomers(SimulationFixtures::sheet());
    $season = new SeasonalFactors(1.0, 30, 26, 0.5);
    $profile = SimulationFixtures::profile()->with(footfall: 5.0);
    $flat = $profile->with(footfallByDayPart: ['morning' => 5.0, 'lunch' => 5.0]);
    $busyAtLunch = $profile->with(footfallByDayPart: ['morning' => 2.0, 'lunch' => 9.0]);
    $ratio = fn ($p) => $potential->forDayPart($p, DayPart::Lunch, $season) / $potential->forDayPart($p, DayPart::Morning, $season);

    // Against flat footfall, lunch gains exactly the surface's 9 : 2.
    expect($ratio($busyAtLunch) / $ratio($flat))->toEqualWithDelta((9 / 2) ** SimulationFixtures::parameters()['demand']['footfall_exponent'], 1e-9)
        // Day parts the surface doesn't cover fall back to the overall footfall and the mix.
        ->and($potential->forDayPart($busyAtLunch, DayPart::Evening, $season))
        ->toBe($potential->forDayPart($profile, DayPart::Evening, $season));
});

it('rejects invalid footfall by day part', function (array $byPart) {
    expect(fn () => SimulationFixtures::profile()->with(footfallByDayPart: $byPart))->toThrow(InvalidArgumentException::class);
})->with([
    'unknown day part' => [['brunch' => 5.0]],
    'above 10' => [['lunch' => 10.5]],
    'negative' => [['night' => -1.0]],
]);
