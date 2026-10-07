<?php

use App\Models\Purchase;
use App\Models\User;
use App\Models\ViabilityReport;
use App\Payments\PaymentGateway;
use App\Payments\Payments;
use Database\Seeders\NeighbourhoodSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/** Stands in for Stripe: records sessions, and says paid for those in $paid. */
class FakeGateway implements PaymentGateway
{
    /** @var list<array<string, mixed>> */
    public array $sessions = [];

    /** @var list<string> */
    public array $paid = [];

    public bool $isConfigured = true;

    public function configured(): bool
    {
        return $this->isConfigured;
    }

    public function checkout(array $params): array
    {
        $this->sessions[] = $params;
        $id = 'cs_test_'.count($this->sessions);

        return ['id' => $id, 'url' => "https://checkout.stripe.test/{$id}"];
    }

    public function session(string $id): array
    {
        return ['id' => $id, 'paid' => in_array($id, $this->paid, true)];
    }

    public function event(string $payload, ?string $signature): array
    {
        if ($signature !== 'good') {
            throw new RuntimeException('bad signature');
        }

        $data = json_decode($payload, true);

        return ['type' => $data['type'], 'session_id' => $data['session'], 'paid' => $data['paid']];
    }
}

beforeEach(function () {
    $this->gateway = new FakeGateway;
    $this->app->instance(PaymentGateway::class, $this->gateway);
    $this->user = User::factory()->create();
});

function doneReport(): ViabilityReport
{
    return ViabilityReport::query()->create(['status' => 'done', 'inputs' => ['lat' => 41.65, 'lng' => -0.88], 'results' => ['runs' => 1, 'years' => 5, 'spot' => [], 'open' => [1 => 0.8]]]);
}

// The viability report --------------------------------------------------------------

it('sends the buyer to Stripe Checkout for the full report, IVA included', function () {
    config(['payments.stripe.tax_rate_id' => 'txr_iva21']);
    $report = doneReport();

    $this->post(route('payments.viability', $report), ['waive_withdrawal' => '1'])
        ->assertRedirect('https://checkout.stripe.test/cs_test_1');

    $purchase = Purchase::query()->sole();
    $session = $this->gateway->sessions[0];

    expect($purchase->only(['product', 'status', 'stripe_session_id', 'viability_report_id', 'amount_cents']))
        ->toBe(['product' => 'viability_report', 'status' => 'pending', 'stripe_session_id' => 'cs_test_1', 'viability_report_id' => $report->id, 'amount_cents' => 2_900])
        ->and($purchase->withdrawal_waived_at)->not->toBeNull()
        ->and($session['line_items'][0]['price_data']['unit_amount'])->toBe(2_900)
        ->and($session['line_items'][0]['tax_rates'])->toBe(['txr_iva21'])
        ->and($session['invoice_creation'])->toBe(['enabled' => true])
        ->and($session['success_url'])->toContain('session_id={CHECKOUT_SESSION_ID}')
        ->and($session['metadata']['purchase_id'])->toBe((string) $purchase->id);
});

it('needs the withdrawal waiver, payments set up, and a finished unpaid report', function () {
    $report = doneReport();

    $this->post(route('payments.viability', $report))->assertSessionHasErrors('waive_withdrawal');

    $this->gateway->isConfigured = false;
    $this->post(route('payments.viability', $report), ['waive_withdrawal' => '1'])->assertSessionHasErrors('payment');
    $this->gateway->isConfigured = true;

    $report->update(['paid_at' => now()]);
    $this->post(route('payments.viability', $report), ['waive_withdrawal' => '1'])->assertSessionHasErrors('payment');

    expect(Purchase::query()->count())->toBe(0);
});

it('unlocks the report when the buyer comes back paid, and not before', function () {
    $report = doneReport();
    $this->post(route('payments.viability', $report), ['waive_withdrawal' => '1']);

    $this->get(route('payments.return', ['session_id' => 'cs_test_1']))->assertRedirect(route('viability.show', $report));
    expect($report->refresh()->paid_at)->toBeNull();

    $this->gateway->paid = ['cs_test_1'];
    $this->get(route('payments.return', ['session_id' => 'cs_test_1']))->assertRedirect(route('viability.show', $report));

    expect($report->refresh()->paid_at)->not->toBeNull()
        ->and(Purchase::query()->sole()->status)->toBe('paid');
});

it('fulfils from Stripe\'s webhook, once, and rejects unsigned calls', function () {
    $report = doneReport();
    $this->post(route('payments.viability', $report), ['waive_withdrawal' => '1']);
    $payload = json_encode(['type' => 'checkout.session.completed', 'session' => 'cs_test_1', 'paid' => true]);

    $this->call('POST', route('payments.webhook'), [], [], [], ['HTTP_STRIPE_SIGNATURE' => 'bad', 'CONTENT_TYPE' => 'application/json'], $payload)->assertStatus(400);
    expect($report->refresh()->paid_at)->toBeNull();

    $this->call('POST', route('payments.webhook'), [], [], [], ['HTTP_STRIPE_SIGNATURE' => 'good', 'CONTENT_TYPE' => 'application/json'], $payload)->assertOk();
    $paidAt = $report->refresh()->paid_at;
    $this->travel(1)->minutes();
    $this->call('POST', route('payments.webhook'), [], [], [], ['HTTP_STRIPE_SIGNATURE' => 'good', 'CONTENT_TYPE' => 'application/json'], $payload)->assertOk();

    expect($paidAt)->not->toBeNull()
        ->and($report->refresh()->paid_at->equalTo($paidAt))->toBeTrue();
});

it('offers the full report on the report page', function () {
    $report = doneReport();

    $this->get(route('viability.show', $report))->assertInertia(fn (Assert $page) => $page
        ->where('price_cents', 2_900)
        ->where('payments_enabled', true)
        ->where('full', null));
});

// Capital tiers -----------------------------------------------------------------------

it('lets an account start with up to €30,000 free, and more once it has bought savings', function () {
    $this->seed(NeighbourhoodSeeder::class);

    expect(Payments::maxCapitalCents($this->user))->toBe(3_000_000);
    $this->actingAs($this->user)->post(route('games.store'), ['starting_capital_euros' => 50_000])->assertSessionHasErrors('starting_capital_euros');

    $this->actingAs($this->user)->post(route('payments.capital'), ['tier' => 'capital_60k', 'waive_withdrawal' => '1'])
        ->assertRedirect('https://checkout.stripe.test/cs_test_1');
    expect($this->gateway->sessions[0]['customer_email'])->toBe($this->user->email);

    $this->gateway->paid = ['cs_test_1'];
    $this->actingAs($this->user)->get(route('payments.return', ['session_id' => 'cs_test_1']))->assertRedirect(route('games.index'));

    expect(Payments::maxCapitalCents($this->user))->toBe(6_000_000);
    $this->actingAs($this->user)->post(route('games.store'), ['starting_capital_euros' => 50_000])->assertSessionHasNoErrors();
    $this->actingAs($this->user)->post(route('payments.capital'), ['tier' => 'capital_60k', 'waive_withdrawal' => '1'])->assertSessionHasErrors('tier');

    $this->actingAs($this->user)->get(route('games.index'))->assertInertia(fn (Assert $page) => $page
        ->where('starting_capital.max_euros', 60_000)
        ->where('starting_capital.free_euros', 30_000)
        ->has('capital_tiers', 1)
        ->where('capital_tiers.0.key', 'capital_100k'));
});

it('needs an account to buy capital', function () {
    $this->post(route('payments.capital'), ['tier' => 'capital_60k', 'waive_withdrawal' => '1'])->assertRedirect(route('login'));
});

// Legal pages ---------------------------------------------------------------------------

it('shows the terms, privacy and disclaimer pages', function (string $route, string $component) {
    $this->get(route($route))->assertOk()->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    ['legal.terms', 'legal/terms'],
    ['legal.privacy', 'legal/privacy'],
    ['legal.disclaimer', 'legal/disclaimer'],
]);
