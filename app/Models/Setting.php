<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable(['key', 'value'])]
class Setting extends Model
{
    public const CACHE_KEY = 'app.settings';

    /**
     * Default values used when a setting has not been saved yet.
     *
     * @var array<string, string>
     */
    public const DEFAULTS = [
        'company_name' => 'KEP Loan',
        'company_email' => '',
        'company_phone' => '',
        'company_address' => '',
        'currency_code' => 'USD',
        'receipt_footer' => 'Thank you for your payment.',
        'early_settlement_fee_percent' => '0',
        'approval_limit_manager_usd' => '',
        'approval_limit_manager_khr' => '',
        'require_maker_checker' => '0',
    ];

    /**
     * Per-request memo so repeated lookups don't hit the cache store.
     *
     * @var array<string, string|null>|null
     */
    private static ?array $resolved = null;

    protected static function booted(): void
    {
        static::saved(fn () => static::flushCache());
        static::deleted(fn () => static::flushCache());
    }

    public static function flushCache(): void
    {
        static::$resolved = null;
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Get all settings merged over their defaults.
     *
     * @return array<string, string|null>
     */
    public static function allValues(): array
    {
        return static::$resolved ??= array_merge(
            self::DEFAULTS,
            Cache::rememberForever(self::CACHE_KEY, fn () => static::query()->pluck('value', 'key')->all()),
        );
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return static::allValues()[$key] ?? $default;
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public static function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
