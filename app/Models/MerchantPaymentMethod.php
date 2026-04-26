<?php

namespace App\Models;

use App\Enums\PaymentMethodType;
use Illuminate\Database\Eloquent\Casts\AsEncryptedArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantPaymentMethod extends Model
{


    protected $fillable = [
        'merchant_id',
        'type',
        'provider',
        'label',
        'details',
        'logo_url',
        'location',
        'location_lat',
        'location_lng',
        'safety_note',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => PaymentMethodType::class,
            'details' => AsEncryptedArrayObject::class,
            'location_lat' => 'decimal:7',
            'location_lng' => 'decimal:7',
            'is_active' => 'boolean',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
