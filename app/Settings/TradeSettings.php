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

    // Sell Flow
    public bool $sell_enabled;
    public bool $sell_cash_trade_enabled;
    public int $sell_default_expiry_minutes;
    public int $sell_anti_spam_stake_usdc;
    public bool $sell_require_stake_public;
    public bool $sell_require_stake_link;
    public bool $sell_require_stake_cash;

    // A9: auto-cancel expired sell trades (backend cron).
    public bool $sell_auto_cancel_expired_enabled;

    // B1: when true, buy flow user-signs mark/confirm/cancel directly.
    // When false, falls back to legacy operator-signed path.
    public bool $buy_user_signed_enabled;

    public static function group(): string
    {
        return 'trade';
    }
}
