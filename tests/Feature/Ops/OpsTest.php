<?php

use App\Models\Purchase;
use App\Models\User;
use App\Models\ViabilityReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

// Health ---------------------------------------------------------------------

it('is healthy with nothing running', function () {
    $this->artisan('app:health')->expectsOutputToContain('All good')->assertSuccessful();
    $this->getJson('/up')->assertOk()->assertJson(['status' => 'up']);
});

it('flags a queue nobody is working', function () {
    DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->subHour()->getTimestamp(), 'created_at' => now()->subHour()->getTimestamp()]);
    config(['app.debug' => false]);

    $this->artisan('app:health')->expectsOutputToContain('is the queue worker running?')->assertFailed();
    $this->getJson('/up')->assertStatus(500)->assertJson(['status' => 'down']);
});

it('flags recently failed jobs, and forgets old ones', function () {
    DB::table('failed_jobs')->insert(['uuid' => 'old', 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()->subDays(2)]);
    $this->artisan('app:health')->assertSuccessful();

    DB::table('failed_jobs')->insert(['uuid' => 'new', 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()->subHour()]);
    $this->artisan('app:health')->expectsOutputToContain('1 job(s) failed')->assertFailed();
});

// Backups --------------------------------------------------------------------

it('backs up the database compressed, and deletes backups past keep_days', function () {
    $dir = storage_path('framework/testing/backups-'.getmypid());
    config(['ops.backup.path' => $dir, 'ops.backup.keep_days' => 14]);
    File::ensureDirectoryExists($dir);
    touch("{$dir}/traspaso-old.sqlite.gz", now()->subDays(15)->getTimestamp());
    touch("{$dir}/traspaso-recent.sqlite.gz", now()->subDays(13)->getTimestamp());
    // A database file of its own: the test database sits in a transaction,
    // and VACUUM can't run inside one.
    $source = "{$dir}/live.sqlite";
    (new PDO("sqlite:{$source}"))->exec("create table users (email text); insert into users values ('kept@example.com')");
    config(['database.connections.live' => ['driver' => 'sqlite', 'database' => $source, 'prefix' => ''], 'ops.backup.connection' => 'live']);

    $this->artisan('app:backup')->assertSuccessful();

    $backups = File::glob("{$dir}/traspaso-2*.sqlite.gz");
    expect($backups)->toHaveCount(1)
        ->and(File::exists("{$dir}/traspaso-old.sqlite.gz"))->toBeFalse()
        ->and(File::exists("{$dir}/traspaso-recent.sqlite.gz"))->toBeTrue();

    // It's a real SQLite database with the data in it.
    $copy = "{$dir}/restored.sqlite";
    file_put_contents($copy, gzdecode(file_get_contents($backups[0])));
    expect((new PDO("sqlite:{$copy}"))->query("select count(*) from users where email = 'kept@example.com'")->fetchColumn())->toBe(1);

    File::deleteDirectory($dir);
});

// GDPR -----------------------------------------------------------------------

it('needs a login to export data', function () {
    $this->get(route('profile.export'))->assertRedirect(route('login'));
});

it('exports purchases and reports, and keeps purchase records when the account goes', function () {
    $user = User::factory()->create();
    $report = ViabilityReport::query()->create(['user_id' => $user->id, 'status' => 'done', 'inputs' => ['lat' => 41.65], 'results' => []]);
    $purchase = Purchase::query()->create(['user_id' => $user->id, 'viability_report_id' => $report->id, 'product' => 'viability_report', 'amount_cents' => 2_900, 'currency' => 'eur', 'status' => 'paid', 'withdrawal_waived_at' => now(), 'paid_at' => now()]);

    $export = $this->actingAs($user)->get(route('profile.export'))->assertOk()->json();

    expect($export['viability_reports'][0]['uuid'])->toBe($report->uuid)
        ->and($export['purchases'][0])->toMatchArray(['product' => 'viability_report', 'amount_cents' => 2_900])
        ->and($export['account'])->not->toHaveKey('password');

    $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password']);

    expect($purchase->refresh()->user_id)->toBeNull()
        ->and($report->refresh()->user_id)->toBeNull();
});

it('prunes unpaid reports and abandoned checkouts, but keeps what was paid for', function () {
    $old = now()->subDays(91);
    $unpaid = ViabilityReport::query()->create(['status' => 'done', 'inputs' => [], 'results' => []]);
    $paid = ViabilityReport::query()->create(['status' => 'done', 'inputs' => [], 'results' => [], 'paid_at' => $old]);
    $recent = ViabilityReport::query()->create(['status' => 'done', 'inputs' => [], 'results' => []]);
    ViabilityReport::query()->whereKey([$unpaid->id, $paid->id])->update(['created_at' => $old]);

    $abandoned = Purchase::query()->create(['product' => 'capital_60k', 'amount_cents' => 499, 'currency' => 'eur', 'withdrawal_waived_at' => $old]);
    $bought = Purchase::query()->create(['product' => 'capital_60k', 'amount_cents' => 499, 'currency' => 'eur', 'status' => 'paid', 'withdrawal_waived_at' => $old, 'paid_at' => $old]);
    Purchase::query()->whereKey([$abandoned->id, $bought->id])->update(['created_at' => $old]);

    $this->artisan('model:prune')->assertSuccessful();

    expect(ViabilityReport::query()->pluck('id')->all())->toEqualCanonicalizing([$paid->id, $recent->id])
        ->and(Purchase::query()->pluck('id')->all())->toBe([$bought->id]);
});
