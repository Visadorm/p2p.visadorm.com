<?php
namespace App\Listeners;

use App\Events\KycDocumentSubmitted;
use App\Services\AdminNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyAdminOnKycSubmitted implements ShouldQueue
{
    public function handle(KycDocumentSubmitted $event): void
    {
        $document = $event->document;
        $document->loadMissing('merchant');

        AdminNotificationService::notifyIf(
            'alert_kyc_pending',
            'New KYC Document',
            "Merchant {$document->merchant->username} submitted a {$document->type->value} document for review.",
            'heroicon-o-identification',
            'warning'
        );
    }
}
