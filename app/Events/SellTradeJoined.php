<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Trade;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SellTradeJoined implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Trade $trade)
    {
    }

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('trade.' . $this->trade->trade_hash);
    }

    public function broadcastAs(): string
    {
        return 'sell-trade-joined';
    }

    public function broadcastWith(): array
    {
        return [
            'trade_hash' => $this->trade->trade_hash,
            'status' => $this->trade->status->value,
            'join_tx_hash' => $this->trade->join_tx_hash,
        ];
    }
}
