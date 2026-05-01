<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\TradeMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TradeMessageSent implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public TradeMessage $message,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('trade.' . $this->message->trade->trade_hash)];
    }

    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'sender_wallet' => $this->message->sender_wallet,
                'sender_role' => $this->message->sender_role,
                'body' => $this->message->body,
                'has_attachment' => ! empty($this->message->attachment_path),
                'created_at' => $this->message->created_at?->toIso8601String(),
            ],
        ];
    }
}
