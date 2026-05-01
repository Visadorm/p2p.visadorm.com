<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradeMessage extends Model
{
    protected $fillable = [
        'trade_id',
        'sender_wallet',
        'sender_role',
        'body',
        'attachment_path',
    ];

    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }
}
