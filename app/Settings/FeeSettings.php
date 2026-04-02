<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class FeeSettings extends Settings
{
    // P2P Trading Fees (display only - actual fees hardcoded in smart contract)
    public float $p2p_fee_percent;

    // Lock Period
    public int $fund_lock_hours;

    public static function group(): string
    {
        return 'fees';
    }
}
