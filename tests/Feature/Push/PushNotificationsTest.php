<?php

use App\Actions\Game\PurchaseBusiness;
use App\Actions\Game\StartGame;
use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\PushSubscription;
use App\Models\User;
use App\Push\NightlyNotifier;
use App\Push\PushSender;
use Database\Seeders\NeighbourhoodSeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/** Records what would be pushed; endpoints listed in $expired report unsubscribed devices. */
class FakePushSender implements PushSender
{
    /** @var list<array{endpoint: string, message: array<string, string>}> */
    public array $sent = [];

    /** @param list<string> $expired */
    public function __construct(public array $expired = []) {}

    public function send(PushSubscription $subscription, array $message): bool
    {
        $this->sent[] = ['endpoint' => $subscription->endpoint, 'message' => $message];

        return ! in_array($subscription->endpoint, $this->expired, true);
    }
}

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-09-30 12:00', 'Europe/Madrid'));
    $this->seed(NeighbourhoodSeeder::class);
    // Fresh from the database, so the notification defaults are loaded.
    $this->user = User::factory()->create()->refresh();
    $this->push = new FakePushSender;
    $this->app->instance(PushSender::class, $this->push);
});

function pushGame(User $user): Game
{
    $game = app(StartGame::class)->handle($user, 10_000_000, seed: 77);
    app(PurchaseBusiness::class)->handle($game, $game->businesses()->orderBy('traspaso_cents')->first());

    return $game->refresh();
}

function device(User $user, string $endpoint = 'https://push.example/1'): PushSubscription
{
    return $user->pushSubscriptions()->create(['endpoint' => $endpoint, 'public_key' => 'key', 'auth_token' => 'auth']);
}

function playNight(string $date): void
{
    test()->travelTo(Carbon::parse("{$date} 23:00", 'Europe/Madrid'));
    test()->artisan('game:nightly', ['--sync' => true])->assertSuccessful();
}

// Devices ----------------------------------------------------------------------

it('stores a device subscription, and moves it to whoever subscribes it next', function () {
    $payload = ['endpoint' => 'https://push.example/abc', 'keys' => ['p256dh' => 'pk', 'auth' => 'ak'], 'content_encoding' => 'aes128gcm'];

    $this->post(route('push.subscribe'), $payload)->assertRedirect(route('login'));
    $this->actingAs($this->user)->postJson(route('push.subscribe'), $payload)->assertNoContent();
    $this->actingAs($this->user)->postJson(route('push.subscribe'), $payload)->assertNoContent();

    $other = User::factory()->create();
    $this->actingAs($other)->postJson(route('push.subscribe'), [...$payload, 'keys' => ['p256dh' => 'pk2', 'auth' => 'ak2']])->assertNoContent();

    expect(PushSubscription::query()->count())->toBe(1)
        ->and(PushSubscription::query()->sole()->only(['user_id', 'public_key']))->toBe(['user_id' => $other->id, 'public_key' => 'pk2']);
});

it('rejects a subscription without its keys or with an insecure endpoint', function () {
    $this->actingAs($this->user)->postJson(route('push.subscribe'), ['endpoint' => 'http://push.example/abc', 'keys' => ['p256dh' => 'pk']])
        ->assertJsonValidationErrors(['endpoint', 'keys.auth']);
});

it('removes a device', function () {
    device($this->user, 'https://push.example/x');

    $this->actingAs($this->user)->deleteJson(route('push.unsubscribe'), ['endpoint' => 'https://push.example/x'])->assertNoContent();

    expect(PushSubscription::query()->count())->toBe(0);
});

// Settings ---------------------------------------------------------------------

it('shows and saves what the player wants to hear about', function () {
    config(['webpush.public_key' => 'PUBLIC']);
    device($this->user);

    $this->actingAs($this->user)->get(route('notifications.edit'))->assertInertia(fn (Assert $page) => $page
        ->component('settings/notifications')
        ->where('notify_daily_results', true)
        ->where('notify_events', true)
        ->where('vapid_public_key', 'PUBLIC')
        ->where('devices', 1)
        ->where('nightly_at', config('game.nightly_at')));

    $this->actingAs($this->user)->patch(route('notifications.update'), ['notify_daily_results' => false, 'notify_events' => true])->assertRedirect();

    expect($this->user->refresh()->notify_daily_results)->toBeFalse()
        ->and($this->user->notify_events)->toBeTrue();
});

// The nightly push -----------------------------------------------------------------

it('sends each device the day\'s results after the nightly run, once', function () {
    $game = pushGame($this->user);
    device($this->user, 'https://push.example/phone');
    device($this->user, 'https://push.example/laptop');

    playNight('2026-10-01');
    playNight('2026-10-01');

    $day = $game->dayResults()->sole();

    expect($this->push->sent)->toHaveCount(2)
        ->and(array_column($this->push->sent, 'endpoint'))->toBe(['https://push.example/phone', 'https://push.example/laptop'])
        ->and($this->push->sent[0]['message']['title'])->toContain($game->business->fictional_name)->toContain('Thu 1 Oct')
        ->and($this->push->sent[0]['message']['body'])->toContain(number_format($day->customers, 0, ',', '.').' customers')
        ->and($this->push->sent[0]['message']['url'])->toBe("/games/{$game->id}");
});

it('forgets devices that have unsubscribed', function () {
    pushGame($this->user);
    device($this->user, 'https://push.example/gone');
    device($this->user, 'https://push.example/here');
    $this->push->expired = ['https://push.example/gone'];

    playNight('2026-10-01');

    expect($this->user->pushSubscriptions()->pluck('endpoint')->all())->toBe(['https://push.example/here']);
});

it('only sends what the player asked for', function () {
    $game = pushGame($this->user);
    device($this->user);
    $this->user->update(['notify_daily_results' => false]);

    playNight('2026-10-01');
    expect($this->push->sent)->toBe([]);

    // An event waiting for a decision still gets through.
    GameEvent::query()->create(['game_id' => $game->id, 'month' => 1, 'type' => 'equipment_failure', 'payload' => [], 'choices' => ['repair', 'limp_on']]);
    playNight('2026-10-02');

    expect($this->push->sent)->toHaveCount(1)
        ->and($this->push->sent[0]['message']['body'])->toBe('Equipment Failure needs your decision.');

    $this->user->update(['notify_events' => false]);
    playNight('2026-10-03');
    expect($this->push->sent)->toHaveCount(1);
});

it('says when a month closes and when a day was closed', function () {
    $game = pushGame($this->user);
    playNight('2026-10-31');

    $notifier = app(NightlyNotifier::class);
    $last = $game->dayResults()->reorder()->latest('date')->first();
    $month = $game->monthResults()->sole();

    expect($notifier->message($game, $last, [])['body'])
        ->toContain(sprintf('Month 1 closed: %s', $month->profit_cents >= 0 ? 'profit' : 'loss'));

    $last->open = false;
    expect($notifier->message($game, $last, [])['body'])->toStartWith('Closed today.');
});

it('always says when the café went bankrupt', function () {
    $game = pushGame($this->user);
    $this->user->update(['notify_daily_results' => false, 'notify_events' => false]);
    device($this->user);
    playNight('2026-10-01');
    $game->update(['status' => GameStatus::Bankrupt]);

    app(NightlyNotifier::class)->notify($game->refresh());

    expect($this->push->sent)->toHaveCount(1)
        ->and($this->push->sent[0]['message']['title'])->toEndWith(': bankrupt');
});

// The app --------------------------------------------------------------------------

it('opens on the latest day\'s results', function () {
    $game = pushGame($this->user);

    $this->actingAs($this->user)->get(route('games.latest'))->assertRedirect(route('games.show', $game));

    playNight('2026-10-02');

    $this->actingAs($this->user)->get(route('games.show', $game))->assertInertia(fn (Assert $page) => $page
        ->where('latest_day.date', '2026-10-02')
        ->where('month_to_date.revenue_cents', (int) $game->dayResults()->sum('revenue_cents'))
        ->has('latest_day.customers'));
});

it('sends a player without a café to the game list', function () {
    $this->actingAs($this->user)->get(route('games.latest'))->assertRedirect(route('games.index'));
});

it('ships an installable manifest, its icons and the service worker', function () {
    $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest['display'])->toBe('standalone')
        ->and($manifest['start_url'])->toBe('/games/latest')
        ->and(collect($manifest['icons'])->pluck('sizes')->all())->toContain('192x192', '512x512')
        ->and(collect($manifest['icons'])->contains('purpose', 'maskable'))->toBeTrue()
        ->and(public_path('sw.js'))->toBeFile();

    foreach ($manifest['icons'] as $icon) {
        expect(public_path($icon['src']))->toBeFile();
    }

    $this->get('/')->assertSee('rel="manifest"', escape: false);
});

it('generates VAPID keys to paste into .env', function () {
    $this->artisan('webpush:vapid')->expectsOutputToContain('WEBPUSH_PUBLIC_KEY=')->assertSuccessful();
});
