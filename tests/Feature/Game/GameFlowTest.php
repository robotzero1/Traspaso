<?php

use App\Actions\Game\SimulateDays;
use App\Actions\Game\StartGame;
use App\Enums\BusinessStatus;
use App\Enums\GameStatus;
use App\Game\GameValuation;
use App\Game\Sales;
use App\Generation\Geo\Geo;
use App\Models\Business;
use App\Models\Game;
use App\Models\User;
use App\Simulation\Costs\MonthlyCosts;
use App\Simulation\Data\CalendarDate;
use App\Simulation\Data\ParameterSheet;
use App\Simulation\Sale\SaleCosts;
use Database\Seeders\NeighbourhoodSeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // Bought on the last day of September, so the café trades from 1 October
    // and its first month is a whole one.
    $this->travelTo(Carbon::parse('2026-09-30 12:00', 'Europe/Madrid'));
    $this->seed(NeighbourhoodSeeder::class);
    $this->user = User::factory()->create();
});

/** A game with a market, started through the action with a fixed seed. */
function startedGame(User $user, int $capitalCents = 5_000_000, int $seed = 2026): Game
{
    return app(StartGame::class)->handle($user, $capitalCents, seed: $seed);
}

/** A business in the game made into a solid, affordable buy, so a test year plays out. */
function solidBusiness(Game $game): Business
{
    $business = $game->businesses()->orderBy('id')->first();
    $business->update([
        'footfall' => 6.5, 'rent_month_cents' => 70_000, 'traspaso_cents' => 1_800_000, 'condition' => 7,
        'category' => 'cafe', 'licence' => 'cafe', 'kitchen' => 'basic', 'indoor_seats' => 30, 'terrace_seats' => 12,
        // Use the overall footfall above, whatever street the business is on.
        'footfall_by_day_part' => null,
    ]);

    return $business->refresh();
}

function decisionsPayload(array $overrides = []): array
{
    return array_merge([
        'price_level' => 1.0,
        'open_day_parts' => ['morning', 'lunch', 'afternoon'],
        'open_days_per_week' => 6,
        'staff_count' => 2,
        'marketing_spend_cents' => 10_000,
        'quality_tier' => 'standard',
    ], $overrides);
}

// Starting --------------------------------------------------------------

it('sends guests to the login page', function () {
    $this->get(route('games.index'))->assertRedirect(route('login'));
});

it('lists your games and the capital range', function () {
    startedGame($this->user);
    startedGame(User::factory()->create());

    $this->actingAs($this->user)->get(route('games.index'))->assertInertia(fn (Assert $page) => $page
        ->component('games/index')
        ->has('games', 1)
        ->where('starting_capital.min_euros', 20_000)
        // Free up to €30,000; more is bought (payments).
        ->where('starting_capital.max_euros', 30_000));
});

it('starts a game with a market of businesses for sale', function () {
    $response = $this->actingAs($this->user)->post(route('games.store'), ['starting_capital_euros' => 30_000]);

    $game = Game::query()->sole();
    $response->assertRedirect(route('games.show', $game));

    expect($game->user_id)->toBe($this->user->id)
        ->and($game->cash_cents)->toBe(3_000_000)
        ->and($game->status)->toBe(GameStatus::Active)
        ->and($game->current_month)->toBe(1)
        ->and($game->businesses()->count())->toBeGreaterThanOrEqual(100)->toBeLessThanOrEqual(200)
        ->and($game->businesses()->where('status', '!=', BusinessStatus::ForSale)->count())->toBe(0);
});

it('rejects starting capital outside €20k–€100k', function (int $euros) {
    $this->actingAs($this->user)->post(route('games.store'), ['starting_capital_euros' => $euros])
        ->assertSessionHasErrors('starting_capital_euros');
})->with([19_999, 100_001]);

it('generates the same market from the same seed', function () {
    $market = fn (Game $g) => $g->businesses()->orderBy('id')->get()
        ->map(fn (Business $b) => $b->only(['fictional_name', 'neighbourhood_id', 'rent_month_cents', 'traspaso_cents', 'footfall']))
        ->all();

    expect($market(startedGame($this->user, seed: 7)))->toBe($market(startedGame($this->user, seed: 7)))
        ->and($market(startedGame($this->user, seed: 7)))->not->toBe($market(startedGame($this->user, seed: 8)));
});

// Browsing and buying ----------------------------------------------------

it('shows the businesses for sale', function () {
    $game = startedGame($this->user);

    $this->actingAs($this->user)->get(route('games.show', $game))->assertInertia(fn (Assert $page) => $page
        ->component('games/show')
        ->where('game.phase', 'browsing')
        ->has('businesses', $game->businesses()->count())
        ->has('businesses.0', fn (Assert $b) => $b->hasAll(['id', 'fictional_name', 'neighbourhood', 'traspaso_cents', 'deposit_cents'])->etc()));
});

it('keeps other players out of your game', function () {
    $game = startedGame($this->user);
    $other = User::factory()->create();

    $this->actingAs($other)->get(route('games.show', $game))->assertForbidden();
    $this->actingAs($other)->post(route('games.months.store', $game))->assertForbidden();
});

it('buys a business: pays traspaso, deposits and fees, sets it up and picks rivals', function () {
    $game = startedGame($this->user);
    $business = solidBusiness($game);
    // Deposit and guarantee, two months' rent each; the lawyer (€800 + 1%
    // of the traspaso) and the licence's change of holder.
    $deposit = 70_000 * 4;
    $fees = 80_000 + 18_000 + 60_270;

    $this->actingAs($this->user)->post(route('games.purchase', $game), ['business_id' => $business->id])
        ->assertRedirect(route('games.show', $game));

    $game->refresh();
    $config = config('market.zaragoza_cafe.competitors');
    // The nearest other businesses within range become rivals.
    $nearby = $game->businesses()->whereKeyNot($business->id)->get()
        ->map(fn (Business $b) => [$b->id, Geo::distanceMetres($business->lat, $business->lng, $b->lat, $b->lng)])
        ->filter(fn (array $pair) => $pair[1] <= $config['distance_metres']['max'])
        ->sortBy(fn (array $pair) => $pair[1])
        ->take($config['nearby_count']);

    expect($game->business_id)->toBe($business->id)
        ->and($game->cash_cents)->toBe(5_000_000 - 1_800_000 - $deposit - $fees)
        ->and($game->deposit_cents)->toBe($deposit)
        ->and($game->decisions['open_day_parts'])->toBe(['morning', 'lunch', 'afternoon'])
        ->and($game->states()->where('month', 0)->exists())->toBeTrue()
        ->and($business->refresh()->status)->toBe(BusinessStatus::OwnedByPlayer)
        ->and($game->competitors()->pluck('business_id')->sort()->values()->all())->toBe($nearby->pluck(0)->sort()->values()->all())
        ->and($game->businesses()->where('status', BusinessStatus::Competitor)->count())->toBe($nearby->count());

    foreach ($game->competitors as $rival) {
        expect($rival->distance_metres)->toBeGreaterThanOrEqual($config['distance_metres']['min'])
            ->toBeLessThanOrEqual($config['distance_metres']['max']);
    }
});

it("won't buy what you can't afford", function () {
    $game = startedGame($this->user, 2_000_000);
    $business = solidBusiness($game);
    $business->update(['traspaso_cents' => 2_000_000]);

    $this->actingAs($this->user)->post(route('games.purchase', $game), ['business_id' => $business->id])
        ->assertSessionHasErrors('business_id');

    expect($game->refresh()->business_id)->toBeNull();
});

it('buys only one business, and only from this game', function () {
    $game = startedGame($this->user);
    $otherGame = startedGame($this->user, seed: 99);

    $this->actingAs($this->user)->post(route('games.purchase', $game), ['business_id' => $otherGame->businesses()->first()->id])
        ->assertSessionHasErrors('business_id');

    $this->actingAs($this->user)->post(route('games.purchase', $game), ['business_id' => solidBusiness($game)->id]);
    $second = $game->businesses()->where('status', BusinessStatus::ForSale)->first();

    $this->actingAs($this->user)->post(route('games.purchase', $game), ['business_id' => $second->id])
        ->assertSessionHasErrors('business_id');
});

// Decisions --------------------------------------------------------------

function boughtGame(User $user, int $seed = 2026): Game
{
    $game = startedGame($user, seed: $seed);
    test()->actingAs($user)->post(route('games.purchase', $game), ['business_id' => solidBusiness($game)->id]);

    return $game->refresh();
}

it('saves decisions for the coming month', function () {
    $game = boughtGame($this->user);

    $this->actingAs($this->user)
        ->put(route('games.decisions', $game), decisionsPayload(['price_level' => 1.1, 'staff_count' => 3, 'quality_tier' => 'premium']))
        ->assertRedirect(route('games.show', $game));

    expect($game->refresh()->decisions)->toMatchArray(['price_level' => 1.1, 'staff_count' => 3, 'quality_tier' => 'premium']);
});

it('rejects decisions outside the limits', function (array $overrides, string $field) {
    $game = boughtGame($this->user);

    $this->actingAs($this->user)->put(route('games.decisions', $game), decisionsPayload($overrides))
        ->assertSessionHasErrors($field);
})->with([
    'price too high' => [['price_level' => 2.5], 'price_level'],
    'no day parts' => [['open_day_parts' => []], 'open_day_parts'],
    'unknown day part' => [['open_day_parts' => ['brunch']], 'open_day_parts.0'],
    'eight days a week' => [['open_days_per_week' => 8], 'open_days_per_week'],
    'too many staff' => [['staff_count' => 50], 'staff_count'],
    'unknown tier' => [['quality_tier' => 'luxury'], 'quality_tier'],
    'night on a café licence' => [['open_day_parts' => ['evening', 'night']], 'open_day_parts'],
    'choice for no event' => [['event_choices' => ['1:equipment_failure' => 'repair']], 'event_choices'],
]);

// Playing ------------------------------------------------------------------

it('plays a month and stores the results', function () {
    $game = boughtGame($this->user);
    $cashBefore = $game->cash_cents;

    $this->actingAs($this->user)->post(route('games.months.store', $game))->assertRedirect(route('games.show', $game));

    $game->refresh();
    $result = $game->monthResults()->sole();

    expect($game->current_month)->toBe(2)
        ->and($result->month)->toBe(1)
        ->and($result->calendar_month)->toBe($game->calendarMonth(1))
        ->and($result->customers)->toBeGreaterThan(0)
        ->and($result->cash_after_cents)->toBe($game->cash_cents)
        ->and($result->owner_pay_cents)->toBe(config('market.zaragoza_cafe.owner.pay_month_cents'))
        ->and($game->cash_cents)->toBe($cashBefore + $result->profit_cents - $result->owner_pay_cents)
        ->and($result->day_parts)->toHaveCount(3)
        ->and($game->states()->where('month', 1)->sole()->decisions['staff_count'])->toBe(config('market.zaragoza_cafe.default_decisions.staff_count'));
});

it('plays the month day by day and stores each day', function () {
    $game = boughtGame($this->user);
    $cashBefore = $game->cash_cents;

    $this->actingAs($this->user)->post(route('games.months.store', $game));

    $game->refresh();
    $month = $game->monthResults()->sole();
    $days = $game->dayResults()->get();
    $first = $game->firstDayOf(1);

    expect($days)->toHaveCount($first->daysInMonth())
        ->and($days->first()->date->toDateString())->toBe($first->toString())
        ->and($days->every(fn ($d) => $d->month === 1))->toBeTrue()
        ->and($days->sum('customers'))->toBe($month->customers)
        ->and($days->sum('revenue_cents'))->toBe($month->revenue_cents)
        ->and($days->sum('cogs_cents'))->toBe($month->cogs_cents)
        // Six days a week: the quietest weekday is closed (unless a holiday) and takes nothing.
        ->and($days->where('open', false)->sum('revenue_cents'))->toBe(0)
        ->and($days->where('open', false)->count())->toBeBetween(3, 5)
        // The month's bills go out on its last day.
        ->and($days->last()->cash_after_cents)->toBe($game->cash_cents)
        ->and($days->first()->cash_after_cents)->toBe($cashBefore + $days->first()->revenue_cents - $days->first()->cogs_cents - $days->first()->event_cost_cents);

    $this->actingAs($this->user)->get(route('games.show', $game))->assertInertia(fn (Assert $page) => $page
        ->has('days', $first->daysInMonth())
        ->has('days.0', fn (Assert $day) => $day->hasAll(['date', 'open', 'weather', 'terrace_usable', 'customers', 'revenue_cents', 'events'])));
});

it('plays on past the first year when the market says games last longer', function () {
    config(['market.zaragoza_cafe.game.months' => 24]);
    $game = boughtGame($this->user);
    $game->update(['current_month' => 13, 'cash_cents' => 10_000_000]);

    $this->actingAs($this->user)->post(route('games.months.store', $game))->assertSessionHasNoErrors();

    expect($game->refresh()->current_month)->toBe(14)
        ->and($game->monthResults()->sole()->month)->toBe(13)
        ->and($game->states()->where('month', 13)->sole()->local_trend)->toBeGreaterThan(0.0);
});

it('shows the business, decisions and results while playing', function () {
    $game = boughtGame($this->user);
    $this->actingAs($this->user)->post(route('games.months.store', $game));

    $this->actingAs($this->user)->get(route('games.show', $game))->assertInertia(fn (Assert $page) => $page
        ->component('games/show')
        ->where('game.phase', 'playing')
        ->where('game.current_month', 2)
        ->has('business')
        ->has('state')
        ->has('decisions')
        ->has('results', 1)
        ->has('competitors')
        ->where('allowed_day_parts', ['morning', 'lunch', 'afternoon', 'evening']));
});

it('plays out the same for the same seed and decisions', function () {
    $play = function (int $seed) {
        $game = boughtGame($this->user, $seed);

        foreach (range(1, 4) as $month) {
            $this->actingAs($this->user)->post(route('games.months.store', $game));
        }

        return $game->monthResults()->get()->map->only(['customers', 'revenue_cents', 'profit_cents', 'events'])->all();
    };

    expect($play(11))->toBe($play(11));
});

/** Only equipment failures, and always. */
function forceEquipmentFailures(): void
{
    $library = config('market.zaragoza_cafe.events.library');
    $forced = array_map(fn ($e) => [...$e, 'probability' => ['base' => 0.0]], $library);
    $forced['equipment_failure']['probability'] = ['base' => 1.0];
    config(['market.zaragoza_cafe.events.library' => $forced]);
}

/** The nightly run, on the given evening (Madrid time). */
function nightlyRun(string $date): void
{
    test()->travelTo(Carbon::parse("{$date} 23:00", 'Europe/Madrid'));
    test()->artisan('game:nightly', ['--sync' => true])->assertSuccessful();
}

// Real time ------------------------------------------------------------------

it('trades from the day after the purchase, one day each night', function () {
    $game = boughtGame($this->user);

    expect($game->started_on->toDateString())->toBe('2026-10-01')
        ->and($game->last_simulated_on->toDateString())->toBe('2026-09-30');

    nightlyRun('2026-10-01');
    nightlyRun('2026-10-01');

    expect($game->refresh()->last_simulated_on->toDateString())->toBe('2026-10-01')
        ->and($game->dayResults()->count())->toBe(1)
        ->and($game->cash_cents)->toBe($game->dayResults()->sole()->cash_after_cents);
});

it('catches up on missed nights, and settles the month on its last day', function () {
    $game = boughtGame($this->user);

    nightlyRun('2026-11-02');

    $game->refresh();
    $october = $game->monthResults()->sole();

    expect($game->dayResults()->count())->toBe(33)
        ->and($october->month)->toBe(1)
        ->and($october->calendar_month)->toBe(10)
        ->and($october->revenue_cents)->toBe((int) $game->dayResults()->where('month', 1)->sum('revenue_cents'))
        ->and($game->dayResults()->whereDate('date', '2026-10-31')->sole()->cash_after_cents)->toBe($october->cash_after_cents)
        ->and($game->current_month)->toBe(2)
        ->and($game->dayResults()->where('month', 2)->count())->toBe(2)
        ->and($game->last_simulated_on->toDateString())->toBe('2026-11-02');
});

it('plays the same days whether caught up at once or night by night', function () {
    $atOnce = boughtGame($this->user, 77);
    $nightly = boughtGame(User::factory()->create(), 77);
    $days = app(SimulateDays::class);

    $days->handle($atOnce, CalendarDate::parse('2026-10-04'));

    foreach (['2026-10-01', '2026-10-02', '2026-10-03', '2026-10-04'] as $date) {
        $days->handle($nightly, CalendarDate::parse($date));
    }

    expect($nightly->dayResults()->pluck('revenue_cents')->all())->toBe($atOnce->dayResults()->pluck('revenue_cents')->all())
        ->and($nightly->refresh()->cash_cents)->toBe($atOnce->refresh()->cash_cents);
});

it('pays the fixed bills pro rata in a month taken over part way through', function () {
    $this->travelTo(Carbon::parse('2026-10-20 12:00', 'Europe/Madrid'));
    $game = boughtGame($this->user);

    nightlyRun('2026-10-31');

    $month = $game->refresh()->monthResults()->sole();
    $pay = config('market.zaragoza_cafe.owner.pay_month_cents');

    expect($game->dayResults()->count())->toBe(11)
        ->and($month->owner_pay_cents)->toBe((int) round($pay * 11 / 31))
        ->and($month->rent_cents)->toBe((int) round(70_000 * 11 / 31));
});

it('applies decisions from the next day, and staff changes after their lead time', function () {
    $game = boughtGame($this->user);
    nightlyRun('2026-10-01');

    $this->travelTo(Carbon::parse('2026-10-02 10:00', 'Europe/Madrid'));
    $this->actingAs($this->user)
        ->put(route('games.decisions', $game), decisionsPayload(['price_level' => 1.1, 'staff_count' => 3]))
        ->assertSessionHasNoErrors();

    $game->refresh();

    expect($game->decisions['price_level'])->toBe(1)
        ->and($game->scheduled_decisions)->toBe([
            ['from' => '2026-10-03', 'changes' => ['price_level' => 1.1]],
            ['from' => '2026-10-10', 'changes' => ['staff_count' => 3]],
        ]);

    $this->actingAs($this->user)->get(route('games.show', $game))->assertInertia(fn (Assert $page) => $page
        ->where('decisions.staff_count', 3)
        ->has('scheduled_decisions', 2));

    nightlyRun('2026-10-03');
    expect($game->refresh()->decisions['price_level'])->toBe(1.1)
        ->and($game->decisions['staff_count'])->toBe(1);

    nightlyRun('2026-10-10');
    expect($game->refresh()->decisions['staff_count'])->toBe(3)
        ->and($game->scheduled_decisions)->toBeNull();
});

it('lets the player answer an event before its deadline', function () {
    forceEquipmentFailures();
    $game = boughtGame($this->user);
    nightlyRun('2026-10-01');

    $this->actingAs($this->user)->get(route('games.show', $game))->assertInertia(fn (Assert $page) => $page
        ->has('pending_events', 1)
        ->where('pending_events.0.key', '1:equipment_failure')
        ->where('pending_events.0.choices', ['repair', 'limp_on']));

    $this->actingAs($this->user)
        ->put(route('games.decisions', $game), decisionsPayload(['event_choices' => ['1:equipment_failure' => 'repair']]))
        ->assertSessionHasNoErrors();
    nightlyRun('2026-10-02');

    $event = $game->events()->where('month', 1)->sole();

    expect($event->choice)->toBe('repair')
        ->and($event->resolved_month)->toBe(1)
        // The choice is used once, then cleared.
        ->and($game->refresh()->decisions['event_choices'])->toBe([]);
});

it('takes the default choice when the player doesn\'t answer in time', function () {
    forceEquipmentFailures();
    $game = boughtGame($this->user);

    nightlyRun('2026-10-03');
    expect($game->events()->sole()->choice)->toBeNull();

    nightlyRun('2026-10-04');
    expect($game->events()->sole()->choice)->toBe('limp_on');
});

it('fast-forwards only when switched on', function () {
    $game = boughtGame($this->user);
    config(['game.fast_forward' => false]);

    $this->actingAs($this->user)->post(route('games.months.store', $game))->assertSessionHasErrors('game');

    expect($game->dayResults()->count())->toBe(0);
});

// Ending -------------------------------------------------------------------

it('plays a full year, then sells the business', function () {
    config(['market.zaragoza_cafe.game.months' => 12]);
    $game = boughtGame($this->user);

    foreach (range(1, 12) as $month) {
        $this->actingAs($this->user)->post(route('games.months.store', $game))->assertSessionHasNoErrors();
    }

    $game->refresh();
    expect($game->status)->toBe(GameStatus::Active)
        ->and($game->current_month)->toBe(13)
        ->and($game->isAwaitingEnd())->toBeTrue();

    // No thirteenth month.
    $this->actingAs($this->user)->post(route('games.months.store', $game))->assertSessionHasErrors('game');

    $this->actingAs($this->user)->get(route('games.show', $game))
        ->assertInertia(fn (Assert $page) => $page->where('game.phase', 'ending')->has('business_value_cents'));

    $netWorth = $this->actingAs($this->user)->get(route('games.show', $game))->viewData('page')['props']['game']['net_worth_cents'];
    $cash = $game->cash_cents;
    $deposit = $game->deposit_cents;

    $this->actingAs($this->user)->post(route('games.end', $game), ['outcome' => 'sell']);
    $game->refresh();

    expect($game->status)->toBe(GameStatus::Finished)
        ->and($game->sold_for_cents)->toBeGreaterThan(0)
        ->and($game->cash_cents)->toBe($cash + $deposit + (new SaleCosts(new ParameterSheet(config('market.zaragoza_cafe'))))->netCents($game->sold_for_cents, $game->business->traspaso_cents, false))
        ->and($game->final_net_worth_cents)->toBe($game->cash_cents)
        ->and($game->final_net_worth_cents)->toBe($netWorth);

    $this->actingAs($this->user)->get(route('games.show', $game))
        ->assertInertia(fn (Assert $page) => $page->where('game.phase', 'over'));
});

it('can keep the business at the end instead', function () {
    config(['market.zaragoza_cafe.game.months' => 12]);
    $game = boughtGame($this->user);
    $game->update(['current_month' => 13]);

    $this->actingAs($this->user)->post(route('games.end', $game), ['outcome' => 'keep']);
    $game->refresh();

    expect($game->status)->toBe(GameStatus::Finished)
        ->and($game->sold_for_cents)->toBeNull()
        ->and($game->final_net_worth_cents)->toBeGreaterThan($game->cash_cents);
});

it("can't end the game before the year is out", function () {
    config(['market.zaragoza_cafe.game.months' => 12]);
    $game = boughtGame($this->user);

    $this->actingAs($this->user)->post(route('games.end', $game), ['outcome' => 'sell'])->assertSessionHasErrors('game');
});

it('goes bankrupt when cash runs out', function () {
    $game = boughtGame($this->user);
    $game->update(['cash_cents' => 100]);
    // Rent no café could cover, wherever it is.
    $game->business->update(['rent_month_cents' => 5_000_000]);
    $this->actingAs($this->user)->put(route('games.decisions', $game), decisionsPayload(['staff_count' => 8, 'marketing_spend_cents' => 300_000]));

    $this->actingAs($this->user)->post(route('games.months.store', $game));
    $game->refresh();

    expect($game->status)->toBe(GameStatus::Bankrupt)
        ->and($game->cash_cents)->toBeLessThan(0)
        ->and($game->final_net_worth_cents)->toBe($game->cash_cents + $game->deposit_cents)
        ->and($game->ended_at)->not->toBeNull();

    $this->actingAs($this->user)->post(route('games.months.store', $game))->assertSessionHasErrors('game');
    $this->actingAs($this->user)->get(route('games.show', $game))
        ->assertInertia(fn (Assert $page) => $page->where('game.phase', 'over')->where('game.status', 'bankrupt'));
});

// The decisions screen -----------------------------------------------------

it('saves decisions and plays the month in one go', function () {
    $game = boughtGame($this->user);

    $this->actingAs($this->user)
        ->put(route('games.decisions', $game), decisionsPayload(['staff_count' => 3, 'and_play' => '1']))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('games.show', $game));

    $game->refresh();

    expect($game->current_month)->toBe(2)
        ->and($game->states()->where('month', 1)->sole()->decisions['staff_count'])->toBe(3)
        ->and($game->decisions)->not->toHaveKey('and_play');
});

it("doesn't play the month when the decisions are invalid", function () {
    $game = boughtGame($this->user);

    $this->actingAs($this->user)
        ->put(route('games.decisions', $game), decisionsPayload(['staff_count' => 99, 'and_play' => '1']))
        ->assertSessionHasErrors('staff_count');

    expect($game->refresh()->current_month)->toBe(1);
});

it('gives the screens the opening cash and cost hints', function () {
    $game = boughtGame($this->user);
    $openingCash = $game->cash_cents;
    $this->actingAs($this->user)->post(route('games.months.store', $game));

    $this->actingAs($this->user)->get(route('games.show', $game))->assertInertia(fn (Assert $page) => $page
        ->where('opening_cash_cents', $openingCash)
        ->where('cost_hints.rent_cents', 70_000)
        ->where('cost_hints.staff_per_person_cents', (new MonthlyCosts(new ParameterSheet(config('market.zaragoza_cafe'))))->staff(1))
        ->has('cost_hints.cogs_share.premium')
        ->where('cost_hints.owner_pay_cents', config('market.zaragoza_cafe.owner.pay_month_cents'))
        ->where('cost_hints.min_on_shift', fn ($v) => (float) $v === (float) config('market.zaragoza_cafe.staff.min_on_shift'))
        ->has('results.0.day_parts', 3));
});

// Going live (milestone 16) --------------------------------------------------

it('reports a missed nightly run as a health problem', function () {
    boughtGame($this->user);

    // Before the night's run the café is a day behind: fine.
    $this->travelTo(Carbon::parse('2026-10-01 22:00', 'Europe/Madrid'));
    $this->artisan('app:health')->assertSuccessful();

    // Next evening it's two days behind: a night was missed.
    $this->travelTo(Carbon::parse('2026-10-02 22:00', 'Europe/Madrid'));
    $this->artisan('app:health')->expectsOutputToContain('missed a nightly run')->assertFailed();

    nightlyRun('2026-10-02');
    $this->artisan('app:health')->assertSuccessful();
});

it('exports the user\'s games, and deleting the account removes them', function () {
    $game = boughtGame($this->user);
    nightlyRun('2026-10-01');

    $export = $this->actingAs($this->user)->get(route('profile.export'))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="traspaso-my-data.json"')
        ->json();

    expect($export['account']['email'])->toBe($this->user->email)
        ->and($export['games'][0]['id'])->toBe($game->id)
        ->and($export['games'][0]['day_results'])->toHaveCount(1);

    $this->actingAs($this->user)->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect('/');

    expect(Game::query()->count())->toBe(0);
});

// Selling (milestone 18) ------------------------------------------------------

it('lists the café, brings offers in the nightly run, and keeps buyers\' limits hidden', function () {
    $game = boughtGame($this->user);

    $this->actingAs($this->user)->post(route('games.sale.store', $game), ['asking' => 5_000, 'agency' => '1'])
        ->assertRedirect(route('games.show', $game));
    $this->actingAs($this->user)->post(route('games.sale.store', $game), ['asking' => 6_000])->assertSessionHasErrors('asking');

    nightlyRun('2026-11-30');

    $offers = $game->liveListing()->offers;
    expect($offers)->not->toBeEmpty()
        ->and($offers->every(fn ($o) => $o->amount_cents <= 500_000 && $o->amount_cents <= $o->limit_cents))->toBeTrue()
        // Unanswered offers lapse after their deadline.
        ->and($offers->where('expires_on', '<', '2026-11-30')->every(fn ($o) => $o->status === 'lapsed'))->toBeTrue();

    $this->actingAs($this->user)->get(route('games.show', $game))->assertInertia(fn (Assert $page) => $page
        ->where('sale.listing.asking_cents', 500_000)
        ->has('sale.listing.offers.0', fn (Assert $o) => $o->hasAll(['id', 'buyer', 'amount_cents', 'status', 'counter_cents', 'made_on', 'expires_on'])->missing('limit_cents'))
        ->where('sale.private.net_cents', fn ($v) => $v > 0));
});

it('completes an accepted sale at the month end after the handover, and ends the game', function () {
    $game = boughtGame($this->user);
    $listing = app(Sales::class)->list($game, 4_000_000, agency: false);
    $offer = $listing->offers()->create(['buyer' => 'Carmen', 'amount_cents' => 3_600_000, 'limit_cents' => 3_800_000, 'made_on' => '2026-10-01', 'expires_on' => '2026-10-06']);
    $this->travelTo(Carbon::parse('2026-10-01 12:00', 'Europe/Madrid'));

    $this->actingAs($this->user)->post(route('games.sale.answer', [$game, $offer]), ['answer' => 'accept'])->assertRedirect();

    $listing->refresh();
    $costs = (new SaleCosts(new ParameterSheet(config('market.zaragoza_cafe'))))->breakdown(3_600_000, $game->business->traspaso_cents, false);
    // Accepted for the next day to be played (1 Oct), +30 days → completes 31 Oct.
    expect($listing->completes_on->toDateString())->toBe('2026-10-31')
        ->and($listing->costs)->toBe($costs)
        ->and(app(GameValuation::class)->netWorthCents($game->refresh()))->toBe($game->cash_cents + $game->deposit_cents + $costs['net_cents']);

    nightlyRun('2026-10-30');
    expect($game->refresh()->isActive())->toBeTrue();
    $cashBefore = $game->cash_cents;
    $deposit = $game->deposit_cents;

    nightlyRun('2026-10-31');
    $game->refresh();
    $month = $game->monthResults()->sole();

    expect($game->status)->toBe(GameStatus::Finished)
        ->and($game->sold_for_cents)->toBe(3_600_000)
        ->and($game->deposit_cents)->toBe(0)
        ->and($game->cash_cents)->toBe($month->cash_after_cents + $costs['net_cents'] + $deposit)
        ->and($game->final_net_worth_cents)->toBe($game->cash_cents)
        ->and($listing->refresh()->completed_on->toDateString())->toBe('2026-10-31')
        ->and($cashBefore)->toBeInt();

    // Nothing more is played.
    nightlyRun('2026-11-02');
    expect($game->dayResults()->count())->toBe(31);
});

it('lets a buyer take a counter-offer within their limit, or walk away', function () {
    $game = boughtGame($this->user);
    $listing = app(Sales::class)->list($game, 4_000_000, agency: false);
    $offer = fn (int $limit) => $listing->offers()->create(['buyer' => 'Javier', 'amount_cents' => 3_000_000, 'limit_cents' => $limit, 'made_on' => '2026-10-01', 'expires_on' => '2026-10-06']);
    $low = $offer(3_200_000);
    $this->travelTo(Carbon::parse('2026-10-01 12:00', 'Europe/Madrid'));

    $answer = fn ($o, array $data) => $this->actingAs($this->user)->post(route('games.sale.answer', [$game, $o]), $data);
    $answer($low, ['answer' => 'counter', 'counter' => 2_000])->assertSessionHasErrors('counter');
    $answer($low, ['answer' => 'counter', 'counter' => 41_000])->assertSessionHasErrors('counter');
    $answer($low, ['answer' => 'counter', 'counter' => 35_000])->assertSessionHasNoErrors();

    nightlyRun('2026-10-01');
    expect($low->refresh()->status)->toBe('walked')
        ->and($listing->refresh()->accepted_on)->toBeNull();

    $high = $offer(3_800_000);
    $answer($high, ['answer' => 'counter', 'counter' => 37_500])->assertSessionHasNoErrors();
    nightlyRun('2026-10-02');

    expect($high->refresh()->status)->toBe('accepted')
        ->and($listing->refresh()->price_cents)->toBe(3_750_000)
        ->and($listing->accepted_on->toDateString())->toBe('2026-10-02');
});

it('withdraws a listing, lapsing its offers, and keeps others out', function () {
    $game = boughtGame($this->user);
    $listing = app(Sales::class)->list($game, 4_000_000, agency: true);
    $offer = $listing->offers()->create(['buyer' => 'Lucía', 'amount_cents' => 3_000_000, 'limit_cents' => 3_500_000, 'made_on' => '2026-10-01', 'expires_on' => '2026-10-06']);

    $this->actingAs(User::factory()->create())->delete(route('games.sale.destroy', $game))->assertForbidden();
    $this->actingAs($this->user)->delete(route('games.sale.destroy', $game))->assertRedirect();

    expect($listing->refresh()->withdrawn_on)->not->toBeNull()
        ->and($offer->refresh()->status)->toBe('lapsed')
        ->and($game->liveListing())->toBeNull();
    $this->actingAs($this->user)->post(route('games.sale.answer', [$game, $offer]), ['answer' => 'accept'])->assertSessionHasErrors('offer');
});

// Closing down and quick sale (milestone 19) -----------------------------------

it('sells to a buyer of last resort at the month end, withdrawing the listing', function () {
    $game = boughtGame($this->user);
    nightlyRun('2026-10-10');
    $listing = app(Sales::class)->list($game->refresh(), 9_000_000, agency: false);
    $price = app(Sales::class)->quickSalePriceCents($game);

    $this->actingAs($this->user)->post(route('games.sale.quick', $game))->assertRedirect();

    $quick = $game->liveListing();
    expect($listing->refresh()->withdrawn_on)->not->toBeNull()
        ->and($quick->quick)->toBeTrue()
        ->and($quick->price_cents)->toBe($price)
        ->and($price)->toBeGreaterThan(0)
        ->and($quick->completes_on->toDateString())->toBe('2026-10-31');

    // Agreed: no closing, listing or second quick sale now.
    $this->actingAs($this->user)->post(route('games.close', $game))->assertSessionHasErrors('close');
    $this->actingAs($this->user)->post(route('games.sale.store', $game), ['asking' => 50_000])->assertSessionHasErrors('asking');

    nightlyRun('2026-10-31');
    expect($game->refresh()->status)->toBe(GameStatus::Finished)
        ->and($game->sold_for_cents)->toBe($price);
});

it('closes down at the month end: notice and severance out, scrap and the deposit in', function () {
    $game = boughtGame($this->user);
    nightlyRun('2026-10-10');

    $this->actingAs($this->user)->post(route('games.close', $game))->assertRedirect();
    expect($game->refresh()->closes_on->toDateString())->toBe('2026-10-31');
    $this->actingAs($this->user)->post(route('games.sale.store', $game), ['asking' => 50_000])->assertSessionHasErrors('asking');

    // Changed their mind, then closed after all.
    $this->actingAs($this->user)->delete(route('games.close.cancel', $game))->assertRedirect();
    expect($game->refresh()->closes_on)->toBeNull();
    $this->actingAs($this->user)->post(route('games.close', $game));

    $deposit = $game->deposit_cents;
    nightlyRun('2026-10-31');
    $game->refresh();
    $month = $game->monthResults()->sole();
    $closure = $game->closure;

    expect($game->status)->toBe(GameStatus::Finished)
        ->and($game->sold_for_cents)->toBeNull()
        ->and($closure['net_cents'])->toBe($closure['scrap_cents'] - $closure['notice_cents'] - $closure['severance_cents'])
        ->and($closure['notice_cents'])->toBe(2 * $game->business->rent_month_cents)
        ->and($game->cash_cents)->toBe($month->cash_after_cents + $closure['net_cents'] + $deposit)
        ->and($game->final_net_worth_cents)->toBe($game->cash_cents);

    $this->actingAs($this->user)->get(route('games.show', $game))->assertInertia(fn (Assert $page) => $page
        ->where('game.phase', 'over')
        ->where('game.closure.net_cents', $closure['net_cents']));
});

// Buying again (milestone 20) -----------------------------------------------------

it('carries on after a sale with the cash left, in today\'s market, where the old café still trades', function () {
    $game = boughtGame($this->user);
    $sold = $game->business;
    app(Sales::class)->quickSale($game);
    nightlyRun('2026-10-31');
    $game->refresh();

    $this->actingAs($this->user)->get(route('games.show', $game))->assertInertia(fn (Assert $page) => $page
        ->where('game.can_buy_again', true)->where('career', []));

    $this->actingAs($this->user)->post(route('games.next', $game))->assertRedirect();
    $next = $game->refresh()->nextGame;

    expect($next->starting_capital_cents)->toBe($game->cash_cents)
        ->and($next->cash_cents)->toBe($game->cash_cents)
        ->and($next->business_id)->toBeNull()
        ->and($next->market_refreshed_on->toDateString())->toBe('2026-10-31')
        ->and($next->businesses()->where('status', BusinessStatus::ForSale)->count())->toBeGreaterThan(50)
        // The café sold on is in the new market as a possible rival, not for sale.
        ->and($next->businesses()->where('status', BusinessStatus::Taken)->sole()->fictional_name)->toBe($sold->fictional_name)
        ->and($game->canBuyAgain())->toBeFalse();

    $this->actingAs($this->user)->post(route('games.next', $game))->assertSessionHasErrors('game');

    $this->actingAs($this->user)->get(route('games.show', $next))->assertInertia(fn (Assert $page) => $page
        ->where('game.phase', 'browsing')
        ->has('career', 2)
        ->where('career.0.outcome', 'sold')
        ->where('career.1.outcome', 'choosing')
        ->where('career.1.current', true)
        ->where('businesses', fn ($b) => collect($b)->doesntContain('id', $next->businesses()->where('status', BusinessStatus::Taken)->value('id'))));
});

it('only carries on after a sale or closure', function () {
    $game = boughtGame($this->user);
    $this->actingAs($this->user)->post(route('games.next', $game))->assertSessionHasErrors('game');

    $game->update(['status' => GameStatus::Bankrupt, 'ended_at' => now()]);
    $this->actingAs($this->user)->post(route('games.next', $game))->assertSessionHasErrors('game');
    $this->actingAs(User::factory()->create())->post(route('games.next', $game))->assertForbidden();
});

it('changes the market week by week while the player is choosing', function () {
    $game = startedGame($this->user);
    $before = $game->businesses()->where('status', BusinessStatus::ForSale)->count();
    $this->actingAs($this->user)->get(route('games.show', $game))->assertOk();
    expect($game->businesses()->count())->toBe($before);

    $this->travelTo(Carbon::parse('2026-10-24 12:00', 'Europe/Madrid'));
    $this->actingAs($this->user)->get(route('games.show', $game))->assertOk();
    $game->refresh();

    expect($game->market_refreshed_on->toDateString())->toBe('2026-10-21')
        ->and($game->businesses()->where('status', BusinessStatus::Taken)->count())->toBeGreaterThan(0)
        ->and($game->businesses()->count())->toBeGreaterThan($before)
        ->and($game->businesses()->max('market_index'))->toBe($game->businesses()->count());

    // Once a café is bought, the market stands still.
    $bought = boughtGame($this->user, seed: 7);
    $count = $bought->businesses()->count();
    $this->travelTo(Carbon::parse('2026-12-24 12:00', 'Europe/Madrid'));
    $this->actingAs($this->user)->get(route('games.show', $bought))->assertOk();
    expect($bought->businesses()->count())->toBe($count);
});
