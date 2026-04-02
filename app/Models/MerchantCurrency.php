<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantCurrency extends Model
{


    protected $fillable = [
        'merchant_id',
        'currency_code',
        'markup_percent',
        'min_amount',
        'max_amount',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'markup_percent' => 'decimal:2',
            'min_amount' => 'decimal:6',
            'max_amount' => 'decimal:6',
            'is_active' => 'boolean',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
