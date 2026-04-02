<?php

namespace App\Models;

use App\Enums\TradingLinkType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MerchantTradingLink extends Model
{


    protected $fillable = [
        'merchant_id',
        'slug',
        'type',
        'is_primary',
        'label',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => TradingLinkType::class,
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function trades(): HasMany
    {
        return $this->hasMany(Trade::class, 'trading_link_id');
    }
}
