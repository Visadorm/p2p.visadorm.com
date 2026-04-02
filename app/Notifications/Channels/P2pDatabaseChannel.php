<?php

namespace App\Notifications\Channels;

use App\Models\P2pNotification;
use Illuminate\Notifications\Notification;

class P2pDatabaseChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        $data = $notification->toP2p($notifiable);

        P2pNotification::create([
            'merchant_id' => $notifiable->id,
            'type' => $data['type'],
            'title' => $data['title'],
            'body' => $data['body'],
            'trade_id' => $data['trade_id'] ?? null,
            'is_read' => false,
            'created_at' => now(),
        ]);
    }
}
