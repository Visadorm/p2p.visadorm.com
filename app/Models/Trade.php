<?php

namespace App\Models;

use App\Enums\BankProofStatus;
use App\Enums\StakePaidBy;
use App\Enums\TradeStatus;
use App\Enums\TradeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Trade extends Model
{


    protected $fillable = [
        'trade_hash',
        'trading_link_id',
        'merchant_id',
        'seller_wallet',
        'buyer_wallet',
        'amount_usdc',
        'amount_fiat',
        'currency_code',
        'exchange_rate',
        'fee_amount',
        'payment_method',
        'is_cash_trade',
        'cash_proof_url',
        'seller_verified_payment',
        'type',
        'status',
        'stake_amount',
        'stake_paid_by',
        'escrow_tx_hash',
        'release_tx_hash',
        'fund_tx_hash',
        'join_tx_hash',
        'mark_paid_tx_hash',
        'cancel_tx_hash',
        'dispute_tx_hash',
        'bank_proof_path',
        'bank_proof_status',
        'buyer_id_path',
        'buyer_id_status',
        'meeting_location',
        'meeting_lat',
        'meeting_lng',
        'nft_token_id',
        'nft_metadata',
        'disputed_at',
        'completed_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => TradeType::class,
            'status' => TradeStatus::class,
            'stake_paid_by' => StakePaidBy::class,
            'bank_proof_status' => BankProofStatus::class,
            'buyer_id_status' => BankProofStatus::class,
            'amount_usdc' => 'decimal:6',
            'amount_fiat' => 'decimal:6',
            'exchange_rate' => 'decimal:6',
            'fee_amount' => 'decimal:6',
            'stake_amount' => 'decimal:6',
            'meeting_lat' => 'decimal:7',
            'meeting_lng' => 'decimal:7',
            'nft_metadata' => 'json',
            'is_cash_trade' => 'boolean',
            'seller_verified_payment' => 'boolean',
            'disputed_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function tradingLink(): BelongsTo
    {
        return $this->belongsTo(MerchantTradingLink::class, 'trading_link_id');
    }

    public function dispute(): HasOne
    {
        return $this->hasOne(Dispute::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class)->where('reviewer_role', 'buyer');
    }

    public function merchantReview(): HasOne
    {
        return $this->hasOne(Review::class)->where('reviewer_role', 'seller');
    }

    public function reviews(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Review::class);
    }
}
