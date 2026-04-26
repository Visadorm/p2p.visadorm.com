<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SellOffer extends Model
{
    protected $fillable = [
        'slug',
        'trade_id',
        'seller_wallet',
        'seller_merchant_id',
        'amount_usdc',
        'amount_remaining_usdc',
        'min_trade_usdc',
        'max_trade_usdc',
        'currency_code',
        'fiat_rate',
        'payment_methods',
        'instructions',
        'require_kyc',
        'is_private',
        'is_active',
        'fund_tx_hash',
        'cancel_tx_hash',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_usdc' => 'decimal:6',
            'amount_remaining_usdc' => 'decimal:6',
            'min_trade_usdc' => 'decimal:6',
            'max_trade_usdc' => 'decimal:6',
            'fiat_rate' => 'decimal:6',
            'payment_methods' => 'array',
            'require_kyc' => 'boolean',
            'is_private' => 'boolean',
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function sellerMerchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'seller_merchant_id');
    }

    public function trades(): HasMany
    {
        return $this->hasMany(Trade::class, 'sell_offer_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('amount_remaining_usdc', '>', 0)
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_private', false);
    }

    public function scopeForCurrency(Builder $query, string $currencyCode): Builder
    {
        return $query->where('currency_code', strtoupper($currencyCode));
    }

    public function scopeForSellerWallet(Builder $query, string $wallet): Builder
    {
        return $query->where('seller_wallet', strtolower($wallet));
    }
}
