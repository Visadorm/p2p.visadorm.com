<?php

namespace App\Models;

use App\Enums\BuyerVerification;
use App\Enums\KycStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

class Merchant extends Model
{
    use Notifiable;

    /**
     * Route notification for Twilio SMS.
     */
    public function routeNotificationForTwilio(): ?string
    {
        return $this->phone;
    }


    protected $fillable = [
        'wallet_address',
        'username',
        'email',
        'bio',
        'avatar',
        'rank_id',
        'is_legendary',
        'kyc_status',
        'bank_verified',
        'email_verified',
        'business_verified',
        'is_fast_responder',
        'has_liquidity',
        'is_active',
        'is_online',
        'last_seen_at',
        'avg_response_minutes',
        'total_trades',
        'total_volume',
        'completion_rate',
        'reliability_score',
        'dispute_rate',
        'buyer_verification',
        'trade_instructions',
        'trade_timer_minutes',
        'notify_bank_proof',
        'notify_buyer_id',
        'notify_email',
        'notify_sms',
        'phone',
        'member_since',
    ];

    protected function casts(): array
    {
        return [
            'kyc_status' => KycStatus::class,
            'buyer_verification' => BuyerVerification::class,
            'is_legendary' => 'boolean',
            'bank_verified' => 'boolean',
            'email_verified' => 'boolean',
            'business_verified' => 'boolean',
            'is_fast_responder' => 'boolean',
            'has_liquidity' => 'boolean',
            'is_active' => 'boolean',
            'is_online' => 'boolean',
            'notify_bank_proof' => 'boolean',
            'notify_buyer_id' => 'boolean',
            'notify_email' => 'boolean',
            'notify_sms' => 'boolean',
            'last_seen_at' => 'datetime',
            'avg_response_minutes' => 'integer',
            'total_trades' => 'integer',
            'total_volume' => 'decimal:6',
            'completion_rate' => 'decimal:2',
            'reliability_score' => 'decimal:1',
            'dispute_rate' => 'decimal:2',
            'trade_timer_minutes' => 'integer',
            'member_since' => 'datetime',
        ];
    }

    public function rank(): BelongsTo
    {
        return $this->belongsTo(MerchantRank::class, 'rank_id');
    }

    public function currencies(): HasMany
    {
        return $this->hasMany(MerchantCurrency::class);
    }

    public function tradingLinks(): HasMany
    {
        return $this->hasMany(MerchantTradingLink::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(MerchantPaymentMethod::class);
    }

    public function trades(): HasMany
    {
        return $this->hasMany(Trade::class);
    }

    /**
     * All trades where this merchant is either seller (merchant_id) or buyer (buyer_wallet).
     */
    public function allTrades(): \Illuminate\Database\Eloquent\Builder
    {
        return Trade::where('merchant_id', $this->id)
            ->orWhere('buyer_wallet', $this->wallet_address);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function kycDocuments(): HasMany
    {
        return $this->hasMany(KycDocument::class);
    }

    public function stats(): HasMany
    {
        return $this->hasMany(MerchantStat::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(P2pNotification::class);
    }
}
