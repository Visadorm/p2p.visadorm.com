<?php

namespace App\Providers;

use App\Contracts\ExchangeRateProvider;
use App\Services\ExchangeRates\ExchangeRateApiProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Spatie\LaravelSettings\Models\SettingsProperty;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\WalletService::class);

        // Swap this binding to switch exchange rate providers application-wide.
        $this->app->bind(ExchangeRateProvider::class, ExchangeRateApiProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        $this->ensureSettingsDefaults();
    }

    /**
     * Defensive: make sure required settings rows exist before any consumer
     * (Filament, Inertia, routes) tries to hydrate the typed Settings classes.
     * Idempotent, no-op if rows already present.
     */
    private function ensureSettingsDefaults(): void
    {
        if ($this->app->runningUnitTests()) {
            return;
        }

        try {
            if (! Schema::hasTable('settings')) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        $defaults = [
            ['general', 'support_url', null],
            ['general', 'homepage_variant', 'classic'],
            ['general', 'weglot_enabled', false],
            ['general', 'weglot_api_key', null],
            ['trade', 'sell_enabled', false],
            ['trade', 'sell_max_offers_per_wallet', 5],
            ['trade', 'sell_max_outstanding_usdc', 50000],
            ['trade', 'sell_kyc_threshold_usdc', 1000],
            ['trade', 'sell_kyc_threshold_window_days', 30],
            ['trade', 'sell_cash_meeting_enabled', false],
            ['trade', 'sell_default_offer_timer_minutes', 60],
        ];

        foreach ($defaults as [$group, $name, $value]) {
            try {
                SettingsProperty::firstOrCreate(
                    ['group' => $group, 'name' => $name],
                    ['payload' => json_encode($value), 'locked' => false],
                );
            } catch (\Throwable) {
                // Ignore — first deploy may race with migration; next boot recovers.
            }
        }
    }
}
