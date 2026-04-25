<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PageStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Page extends Model
{
    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('public_pages_nav'));
        static::deleted(fn () => Cache::forget('public_pages_nav'));
    }

    protected $fillable = [
        'title',
        'slug',
        'body',
        'excerpt',
        'cover_image',
        'status',
        'show_in_header',
        'show_in_footer',
        'sort_order',
        'meta_title',
        'meta_description',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PageStatus::class,
            'show_in_header' => 'boolean',
            'show_in_footer' => 'boolean',
            'sort_order' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PageStatus::Published)
            ->where('published_at', '<=', now());
    }

    public function scopeHeaderPages(Builder $query): Builder
    {
        return $query->published()->where('show_in_header', true);
    }

    public function scopeFooterPages(Builder $query): Builder
    {
        return $query->published()->where('show_in_footer', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }
}
