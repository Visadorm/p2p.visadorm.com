<?php
namespace App\Listeners;

use App\Events\TradeInitiated;
use App\Services\AdminNotificationService;
use App\Settings\NotificationSettings;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyAdminOnLargeTrade implements ShouldQueue
{
    public function handle(TradeInitiated $event): void
    {
        $trade = $event->trade;
        $trade->loadMissing('merchant');

        $settings = rescue(fn () => app(NotificationSettings::class), null);
        $threshold = $settings?->large_trade_threshold ?? 10000;

        if ((float) $trade->amount_usdc < $threshold) {
            return; // Not large enough
        }

        AdminNotificationService::notifyIf(
            'alert_large_trade',
            'Large Trade Initiated',
            "Trade for {$trade->amount_usdc} USDC by merchant {$trade->merchant->username} exceeds the {$threshold} USDC threshold.",
            'heroicon-o-currency-dollar',
            'warning'
        );
    }
}
