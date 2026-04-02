<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantStat extends Model
{


    public $timestamps = false;

    protected $fillable = [
        'merchant_id',
        'date',
        'trades_count',
        'volume',
        'completed_count',
        'disputed_count',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'trades_count' => 'integer',
            'volume' => 'decimal:6',
            'completed_count' => 'integer',
            'disputed_count' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
