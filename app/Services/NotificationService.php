<?php

namespace App\Services;

use App\Models\Merchant;
use App\Models\P2pNotification;

class NotificationService
{
    /**
     * Create a new P2P notification for a merchant.
     */
    public function create(
        Merchant $merchant,
        string $type,
        string $title,
        string $body,
        ?int $tradeId = null,
    ): P2pNotification {
        return P2pNotification::create([
            'merchant_id' => $merchant->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'trade_id' => $tradeId,
            'is_read' => false,
            'created_at' => now(),
        ]);
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(P2pNotification $notification): void
    {
        $notification->update(['is_read' => true]);
    }

    /**
     * Mark all unread notifications as read for a merchant.
     */
    public function markAllRead(Merchant $merchant): void
    {
        $merchant->notifications()
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }

    /**
     * Get the count of unread notifications for a merchant.
     */
    public function getUnreadCount(Merchant $merchant): int
    {
        return $merchant->notifications()
            ->where('is_read', false)
            ->count();
    }
}
