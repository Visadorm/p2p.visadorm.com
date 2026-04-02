<?php

namespace App\Events;

use App\Models\Trade;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentMarked implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Trade $trade,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('trade.' . $this->trade->trade_hash)];
    }

    public function broadcastWith(): array
    {
        return [
            'trade_hash' => $this->trade->trade_hash,
            'status' => $this->trade->status->value ?? $this->trade->status,
            'updated_at' => now()->toISOString(),
        ];
    }
}
