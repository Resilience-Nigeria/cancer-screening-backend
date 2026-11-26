<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobileAppSetting extends Model
{
    protected $table = 'mobile_app_settings';
    protected $fillable = ['key', 'value', 'updatedById'];
    protected $casts = ['value' => 'array'];

    public static function get(string $key, array $default = []): array
    {
        $row = static::where('key', $key)->first();
        return array_merge($default, (array) ($row?->value ?? []));
    }

    public static function put(string $key, array $value, ?int $by = null): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value, 'updatedById' => $by]);
    }
}
