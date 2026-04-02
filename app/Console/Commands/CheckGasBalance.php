<?php

namespace App\Console\Commands;

use App\Services\AdminNotificationService;
use App\Services\BlockchainService;
use App\Settings\BlockchainSettings;
use App\Settings\NotificationSettings;
use Illuminate\Console\Command;

class CheckGasBalance extends Command
{
    protected $signature = 'gas:check';
    protected $description = 'Check operator gas wallet balance and alert admins if low';

    public function handle(): void
    {
        $blockchain = app(BlockchainSettings::class);
        $minBalance = (float) ($blockchain->min_gas_balance ?? 0.01);

        if (! $blockchain->gas_wallet_address) {
            return;
        }

        try {
            $weiString = app(BlockchainService::class)->getEthBalance($blockchain->gas_wallet_address);
            $ethBalance = (float) bcdiv($weiString, '1000000000000000000', 18);
        } catch (\Throwable) {
            return;
        }

        if ($ethBalance < $minBalance) {
            AdminNotificationService::notifyIf(
                'alert_low_gas',
                'Low Gas Balance',
                "Operator gas wallet has {$ethBalance} ETH — below the {$minBalance} ETH threshold. Fund it to avoid failed transactions.",
                'heroicon-o-fire',
                'danger'
            );

            $this->warn("Gas balance low: {$ethBalance} ETH (min: {$minBalance})");
        } else {
            $this->info("Gas balance OK: {$ethBalance} ETH");
        }
    }
}
