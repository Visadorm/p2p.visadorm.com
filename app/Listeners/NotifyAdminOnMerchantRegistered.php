<?php
namespace App\Listeners;

use App\Services\AdminNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyAdminOnMerchantRegistered implements ShouldQueue
{
    public function handle(object $event): void
    {
        // This is called manually, not via event auto-discovery
    }

    /**
     * Called directly from AuthController when a new merchant is created.
     */
    public static function notify(string $username, string $wallet): void
    {
        AdminNotificationService::notifyIf(
            'alert_kyc_pending', // Reuse KYC toggle for merchant registration awareness
            'New Merchant Registered',
            "Merchant {$username} ({$wallet}) has registered.",
            'heroicon-o-user-plus',
            'info'
        );
    }
}
