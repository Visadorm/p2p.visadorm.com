<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class MessageTemplate extends Model
{
    protected $fillable = [
        'type', 'label', 'email_subject', 'email_body', 'sms_text', 'variables_guide',
    ];

    public static function resolve(string $type): ?self
    {
        $data = Cache::remember("msg_template:{$type}", 3600, function () use ($type) {
            return static::where('type', $type)->first()?->toArray();
        });

        if (! $data) {
            return null;
        }

        return (new static)->forceFill($data);
    }

    public static function clearCache(string $type): void
    {
        Cache::forget("msg_template:{$type}");
    }
}
