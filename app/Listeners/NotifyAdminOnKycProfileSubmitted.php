<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\KycProfileSubmitted;
use App\Services\AdminNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyAdminOnKycProfileSubmitted implements ShouldQueue
{
    public function handle(KycProfileSubmitted $event): void
    {
        $merchant = $event->merchant;

        AdminNotificationService::notifyIf(
            'alert_kyc_pending',
            'New KYC Identity Profile',
            "Merchant {$merchant->username} submitted identity profile (legal name, DOB, address, country) for review.",
            'heroicon-o-identification',
            'warning'
        );
    }
}
