<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Trade Defaults
        $this->migrator->add('trade.default_trade_timer_minutes', 30);
        $this->migrator->add('trade.max_trade_timer_minutes', 120);
        $this->migrator->add('trade.stake_amount', 5.0);
        $this->migrator->add('trade.global_min_trade', 10.0);
        $this->migrator->add('trade.global_max_trade', 100000.0);

        // Escrow
        $this->migrator->add('trade.liquidity_badge_threshold', 1000.0);
        $this->migrator->add('trade.fast_responder_minutes', 5);

        // Dispute
        $this->migrator->add('trade.dispute_window_hours', 24);
        $this->migrator->add('trade.trade_expiry_cleanup_minutes', 15);

        // Buyer Verification
        $this->migrator->add('trade.default_buyer_verification', 'optional');
    }
};
