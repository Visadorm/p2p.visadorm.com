<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MerchantRank extends Model
{


    public $timestamps = false;

    protected $fillable = [
        'name',
        'slug',
        'min_trades',
        'min_completion_rate',
        'min_volume',
        'badge_icon',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'min_trades' => 'integer',
            'min_completion_rate' => 'decimal:2',
            'min_volume' => 'decimal:6',
            'sort_order' => 'integer',
        ];
    }

    public function merchants(): HasMany
    {
        return $this->hasMany(Merchant::class, 'rank_id');
    }
}
