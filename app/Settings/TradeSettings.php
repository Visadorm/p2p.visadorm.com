<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class TradeSettings extends Settings
{
    // Trade Defaults
    public int $default_trade_timer_minutes;
    public int $max_trade_timer_minutes;
    public float $stake_amount;
    public float $global_min_trade;
    public float $global_max_trade;

    // Escrow
    public float $liquidity_badge_threshold;
    public int $fast_responder_minutes;

    // Cleanup
    public int $trade_expiry_cleanup_minutes;

    // Buyer Verification
    public string $default_buyer_verification;

    public static function group(): string
    {
        return 'trade';
    }
}
