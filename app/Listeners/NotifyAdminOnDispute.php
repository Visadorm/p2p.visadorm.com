<?php
namespace App\Listeners;

use App\Events\DisputeOpened;
use App\Services\AdminNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyAdminOnDispute implements ShouldQueue
{
    public function handle(DisputeOpened $event): void
    {
        $dispute = $event->dispute;
        $dispute->loadMissing('trade.merchant');
        $trade = $dispute->trade;

        AdminNotificationService::notifyIf(
            'alert_new_dispute',
            'New Dispute Opened',
            "Dispute on trade {$trade->amount_usdc} USDC (merchant: {$trade->merchant->username}). Reason: " . str($dispute->reason)->limit(80),
            'heroicon-o-exclamation-triangle',
            'danger'
        );
    }
}
