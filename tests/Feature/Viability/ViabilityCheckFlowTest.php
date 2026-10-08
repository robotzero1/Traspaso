<?php

use App\Jobs\RunViabilityCheck;
use App\Models\FootfallPoint;
use App\Models\ViabilityReport;
use Database\Seeders\FootfallPointSeeder;
use Database\Seeders\NeighbourhoodSeeder;
use Database\Seeders\PointOfInterestSeeder;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed([NeighbourhoodSeeder::class, PointOfInterestSeeder::class, FootfallPointSeeder::class]);
    config(['viability.runs' => 5]);
    $this->point = FootfallPoint::query()->orderBy('id')->first();
});

function checkPayload(FootfallPoint $point, array $overrides = []): array
{
    return array_merge([
        'lat' => $point->lat, 'lng' => $point->lng, 'traspaso_euros' => 30000, 'rent_euros' => 900,
        'floor_area_m2' => 60, 'indoor_seats' => 30, 'terrace_seats' => 8, 'licence' => 'cafe_bar',
        'kitchen' => 'basic', 'condition' => 6, 'capital_euros' => 50000,
        'open_day_parts' => ['morning', 'lunch', 'afternoon'], 'staff_count' => 1, 'quality_tier' => 'standard',
    ], $overrides);
}

it('shows the form to anyone, with the map and its attribution', function () {
    $this->get(route('viability.create'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('viability/create')
        ->where('map.attribution', fn (string $a) => str_contains($a, 'OpenStreetMap'))
        ->where('runs', 5)
        ->has('day_parts', 5));
});

it('queues a check and sends the user to its report', function () {
    Queue::fake();

    $response = $this->post(route('viability.store'), checkPayload($this->point));
    $report = ViabilityReport::query()->sole();

    $response->assertRedirect(route('viability.show', $report));
    Queue::assertPushed(RunViabilityCheck::class, fn ($job) => $job->reportId === $report->id);

    expect($report->status)->toBe('queued')
        ->and($report->inputs['traspaso_cents'])->toBe(3_000_000)
        ->and($report->inputs['open_day_parts'])->toBe(['morning', 'lunch', 'afternoon'])
        ->and($report->user_id)->toBeNull();
});

it('refuses a pin off the shopping streets, too little money, and night hours on a café licence', function () {
    $this->post(route('viability.store'), checkPayload($this->point, ['lat' => 41.0, 'lng' => -1.5]))->assertSessionHasErrors('lat');
    $this->post(route('viability.store'), checkPayload($this->point, ['capital_euros' => 30000]))->assertSessionHasErrors('capital_euros');
    // Enough for the traspaso, the deposit and the guarantee (€33,600), not the fees.
    $this->post(route('viability.store'), checkPayload($this->point, ['capital_euros' => 34000]))->assertSessionHasErrors('capital_euros');
    $this->post(route('viability.store'), checkPayload($this->point, ['licence' => 'cafe', 'open_day_parts' => ['evening', 'night']]))->assertSessionHasErrors('open_day_parts');

    expect(ViabilityReport::query()->count())->toBe(0);
});

it('runs the check and shows the free preview, keeping the full report until it is paid for', function () {
    $this->post(route('viability.store'), checkPayload($this->point));
    $report = ViabilityReport::query()->sole();

    expect($report->status)->toBe('done')
        ->and($report->results['runs'])->toBe(5);

    $this->get(route('viability.show', $report))->assertInertia(fn (Assert $page) => $page
        ->component('viability/show')
        ->where('report.status', 'done')
        ->where('report.unlocked', false)
        ->where('preview.spot.neighbourhood', $this->point->neighbourhood->name)
        ->has('preview.open_year_1')
        ->where('full', null));

    $report->update(['paid_at' => now()]);

    $this->get(route('viability.show', $report))->assertInertia(fn (Assert $page) => $page
        ->where('report.unlocked', true)
        ->has('full.profit_by_year', 5)
        ->has('full.open.5'));
});

it('can show everything for local testing', function () {
    config(['viability.unlock_all' => true]);
    $this->post(route('viability.store'), checkPayload($this->point));

    $this->get(route('viability.show', ViabilityReport::query()->sole()))
        ->assertInertia(fn (Assert $page) => $page->has('full.payback'));
});

it('marks a check that went wrong as failed', function () {
    $report = ViabilityReport::query()->create(['inputs' => [...checkPayload($this->point), 'lat' => 0, 'lng' => 0]]);

    (new RunViabilityCheck($report->id))->failed(new RuntimeException('boom'));

    expect($report->refresh()->status)->toBe('failed');
    $this->get(route('viability.show', $report))->assertInertia(fn (Assert $page) => $page->where('report.status', 'failed')->where('preview', null));
});
