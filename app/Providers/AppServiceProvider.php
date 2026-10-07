<?php

namespace App\Providers;

use App\Generation\BusinessGenerator;
use App\Ops\HealthCheck;
use App\Payments\PaymentGateway;
use App\Payments\StripeGateway;
use App\Push\PushSender;
use App\Push\WebPushSender;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The MVP has one market; the generator itself is market-agnostic.
        $this->app->bind(BusinessGenerator::class, fn () => new BusinessGenerator(config('market.zaragoza_cafe')));
        $this->app->bind(PushSender::class, WebPushSender::class);
        $this->app->bind(PaymentGateway::class, StripeGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // /up reports what HealthCheck finds (for an uptime monitor).
        Event::listen(DiagnosingHealth::class, function () {
            if ($problems = app(HealthCheck::class)->problems()) {
                throw new RuntimeException(implode(' ', $problems));
            }
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Behind the host's HTTPS proxy, links must still be https.
        URL::forceHttps(app()->isProduction());

        if ($proxies = config('ops.trusted_proxies')) {
            TrustProxies::at($proxies === '*' ? '*' : explode(',', $proxies));
        }

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
