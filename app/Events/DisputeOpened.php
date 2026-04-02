<?php

namespace App\Events;

use App\Models\Dispute;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DisputeOpened implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Dispute $dispute,
    ) {}

    public function broadcastOn(): array
    {
        $tradeHash = $this->dispute->trade?->trade_hash;

        return $tradeHash
            ? [new Channel('trade.' . $tradeHash)]
            : [];
    }

    public function broadcastWith(): array
    {
        return [
            'trade_hash' => $this->dispute->trade?->trade_hash,
            'status' => 'disputed',
            'dispute_id' => $this->dispute->id,
            'updated_at' => now()->toISOString(),
        ];
    }
}
