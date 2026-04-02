<?php

namespace App\Models;

use App\Enums\DisputeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dispute extends Model
{


    protected $fillable = [
        'trade_id',
        'opened_by',
        'reason',
        'evidence',
        'status',
        'resolution_tx_hash',
        'resolved_by',
        'resolution_notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => DisputeStatus::class,
            'evidence' => 'json',
        ];
    }

    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }
}
