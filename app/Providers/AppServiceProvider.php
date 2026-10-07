<?php

namespace App\Providers;

use App\Generation\BusinessGenerator;
use App\Payments\PaymentGateway;
use App\Payments\StripeGateway;
use App\Push\PushSender;
use App\Push\WebPushSender;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

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
