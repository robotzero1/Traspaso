<?php

use App\Actions\Game\StartGame;
use App\Enums\BusinessStatus;
use App\Enums\GameStatus;
use App\Generation\Geo\Geo;
use App\Models\Business;
use App\Models\Game;
use App\Models\User;
use App\Simulation\Costs\MonthlyCosts;
use App\Simulation\Data\ParameterSheet;
use Database\Seeders\NeighbourhoodSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
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
        ->where('starting_capital.max_euros', 100_000));
});

it('starts a game with a market of businesses for sale', function () {
    $response = $this->actingAs($this->user)->post(route('games.store'), ['starting_capital_euros' => 40_000]);

    $game = Game::query()->sole();
    $response->assertRedirect(route('games.show', $game));

    expect($game->user_id)->toBe($this->user->id)
        ->and($game->cash_cents)->toBe(4_000_000)
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

it('buys a business: pays traspaso and deposit, sets it up and picks rivals', function () {
    $game = startedGame($this->user);
    $business = solidBusiness($game);
    $deposit = 70_000 * 2;

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
        ->and($game->cash_cents)->toBe(5_000_000 - 1_800_000 - $deposit)
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

it('lets the player answer an event before the next month', function () {
    // Only equipment failures, and always.
    $library = config('market.zaragoza_cafe.events.library');
    $forced = array_map(fn ($e) => [...$e, 'probability' => ['base' => 0.0]], $library);
    $forced['equipment_failure']['probability'] = ['base' => 1.0];
    config(['market.zaragoza_cafe.events.library' => $forced]);

    $game = boughtGame($this->user);
    $this->actingAs($this->user)->post(route('games.months.store', $game));

    $this->actingAs($this->user)->get(route('games.show', $game))->assertInertia(fn (Assert $page) => $page
        ->has('pending_events', 1)
        ->where('pending_events.0.key', '1:equipment_failure')
        ->where('pending_events.0.choices', ['repair', 'limp_on']));

    $this->actingAs($this->user)
        ->put(route('games.decisions', $game), decisionsPayload(['event_choices' => ['1:equipment_failure' => 'repair']]))
        ->assertSessionHasNoErrors();
    $this->actingAs($this->user)->post(route('games.months.store', $game));

    $event = $game->events()->where('month', 1)->sole();

    expect($event->type)->toBe('equipment_failure')
        ->and($event->choice)->toBe('repair')
        ->and($event->resolved_month)->toBe(2)
        // The choice is used once, then cleared.
        ->and($game->refresh()->decisions['event_choices'])->toBe([]);
});

// Ending -------------------------------------------------------------------

it('plays a full year, then sells the business', function () {
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
        ->and($game->cash_cents)->toBe($cash + $deposit + $game->sold_for_cents)
        ->and($game->final_net_worth_cents)->toBe($game->cash_cents)
        ->and($game->final_net_worth_cents)->toBe($netWorth);

    $this->actingAs($this->user)->get(route('games.show', $game))
        ->assertInertia(fn (Assert $page) => $page->where('game.phase', 'over'));
});

it('can keep the business at the end instead', function () {
    $game = boughtGame($this->user);
    $game->update(['current_month' => 13]);

    $this->actingAs($this->user)->post(route('games.end', $game), ['outcome' => 'keep']);
    $game->refresh();

    expect($game->status)->toBe(GameStatus::Finished)
        ->and($game->sold_for_cents)->toBeNull()
        ->and($game->final_net_worth_cents)->toBeGreaterThan($game->cash_cents);
});

it("can't end the game before the year is out", function () {
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
