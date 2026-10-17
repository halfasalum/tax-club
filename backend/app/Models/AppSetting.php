<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Once;

/**
 * A programme setting changed by administrators, with a default in code.
 */
class AppSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value', 'updated_by'];

    protected $casts = ['value' => 'array'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = once(fn () => static::all()->keyBy('key'))->get($key);

        return $setting ? ($setting->value['v'] ?? $default) : $default;
    }

    public static function put(string $key, mixed $value, ?User $by = null): void
    {
        static::updateOrCreate(['key' => $key], ['value' => ['v' => $value], 'updated_by' => $by?->user_id]);
        Once::flush();
    }
}
