<?php

use App\Models\PedestrianCount;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-09 09:30', 'Europe/Madrid'));
    $this->user = User::factory()->create();
});

$spot = ['lat' => 41.6517, 'lng' => -0.8792, 'day_part' => 'morning', 'minutes' => 10, 'count' => 143];

it('shows the counting page with the day parts and the map', function () {
    $this->get(route('counts.index'))->assertRedirect(route('login'));

    $this->actingAs($this->user)->get(route('counts.index'))->assertInertia(fn (Assert $page) => $page
        ->component('counts/index')
        ->has('day_parts', 5)
        ->has('map.attribution')
        ->where('counts', []));
});

it('saves a count with today\'s date, and keeps out nonsense', function () use ($spot) {
    $this->actingAs($this->user)->post(route('counts.store'), [...$spot, 'note' => 'market day'])->assertRedirect(route('counts.index'));

    expect(PedestrianCount::query()->sole())
        ->user_id->toBe($this->user->id)
        ->counted_on->toDateString()->toBe('2026-10-09')
        ->count->toBe(143)
        ->note->toBe('market day');

    $this->actingAs($this->user)->post(route('counts.store'), [...$spot, 'lat' => 40.4])->assertSessionHasErrors('lat');
    $this->actingAs($this->user)->post(route('counts.store'), [...$spot, 'day_part' => 'brunch'])->assertSessionHasErrors('day_part');
    $this->actingAs($this->user)->post(route('counts.store'), [...$spot, 'minutes' => 0])->assertSessionHasErrors('minutes');
});

it('lets each person delete only their own counts', function () use ($spot) {
    $count = $this->user->pedestrianCounts()->create([...$spot, 'counted_on' => '2026-10-09']);

    $this->actingAs(User::factory()->create())->delete(route('counts.destroy', $count))->assertForbidden();
    $this->actingAs($this->user)->delete(route('counts.destroy', $count))->assertRedirect();

    expect(PedestrianCount::query()->count())->toBe(0);
});

it('exports counts into the CSV geo:calibrate reads, once each, keeping its header and rows', function () use ($spot) {
    $relative = 'storage/framework/testing/geo-'.getmypid();
    $dir = base_path($relative);
    File::ensureDirectoryExists($dir);
    config(['geo.sources_path' => $relative]);
    File::put("{$dir}/pedestrian_counts.csv", "# Manual counts\nlat,lng,day_part,date,minutes,count,notes\n41.650000,-0.880000,lunch,2026-09-01,10,80,\n");
    $this->user->pedestrianCounts()->create([...$spot, 'counted_on' => '2026-10-09', 'note' => 'sunny, busy']);

    $this->artisan('geo:counts-export')->expectsOutputToContain('1 new count(s) added; 2 in')->assertSuccessful();
    $this->artisan('geo:counts-export')->expectsOutputToContain('0 new count(s) added; 2 in')->assertSuccessful();

    expect(file("{$dir}/pedestrian_counts.csv", FILE_IGNORE_NEW_LINES))->toBe([
        '# Manual counts',
        'lat,lng,day_part,date,minutes,count,notes',
        '41.650000,-0.880000,lunch,2026-09-01,10,80,',
        '41.651700,-0.879200,morning,2026-10-09,10,143,"sunny, busy"',
    ]);

    File::deleteDirectory($dir);
});
