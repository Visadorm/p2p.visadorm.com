<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('trade.sell_enabled', true);
        $this->migrator->add('trade.sell_max_offers_per_wallet', 5);
        $this->migrator->add('trade.sell_max_outstanding_usdc', 50000);
        $this->migrator->add('trade.sell_kyc_threshold_usdc', 1000);
        $this->migrator->add('trade.sell_kyc_threshold_window_days', 30);
        $this->migrator->add('trade.sell_cash_meeting_enabled', false);
        $this->migrator->add('trade.sell_default_offer_timer_minutes', 60);
    }
};
