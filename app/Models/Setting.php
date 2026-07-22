<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $table = 'system_settings';
    protected $primaryKey = 'settingId';

    protected $fillable = [
        'key', 'value', 'type', 'group', 'label', 'description', 'options',
    ];

    protected $casts = [
        'options' => 'array',
    ];

    /**
     * Read a setting's value by key, cast to its declared type, with a
     * fallback if the key doesn't exist. Cached briefly since this gets
     * called from things like the follow-up reminder command on every
     * run, not just admin pages.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = Cache::remember("setting:{$key}", 300, function () use ($key) {
            return static::where('key', $key)->first();
        });

        if (!$setting) {
            return $default;
        }

        return match ($setting->type) {
            'boolean' => (bool) $setting->value,
            'integer' => (int) $setting->value,
            default => $setting->value,
        };
    }

    public static function setValue(string $key, mixed $value): void
    {
        static::where('key', $key)->update(['value' => (string) $value]);
        Cache::forget("setting:{$key}");
    }
}
