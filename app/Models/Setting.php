<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $guarded = [];

    public const DEFAULTS = [
        'office_name' => 'PT Tri Sakti Lautan Mas',
        'office_location' => 'Kantor pusat · Ketapang',
        'office_address' => '',
        'reception_email' => 'resepsionis@example.com',
        'timezone' => 'Asia/Jakarta',
        'require_confirmation' => true,
        'checkout_reminder' => true,
        'phone_export_enabled' => false,
    ];

    private const CACHE = 'settings.cache';

    /** Seluruh pengaturan dimuat sekali per request (satu query), bukan satu query per pemanggilan get(). */
    private static function loaded(): array
    {
        $app = app();
        if (! $app->bound(self::CACHE)) {
            $app->instance(self::CACHE, static::query()->pluck('value', 'key')
                ->map(fn ($v) => json_decode((string) $v, true))->all());
        }

        return $app->make(self::CACHE);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::loaded();

        return array_key_exists($key, $all) ? $all[$key] : (self::DEFAULTS[$key] ?? $default);
    }

    public static function bool(string $key, bool $default = false): bool
    {
        return (bool) static::get($key, $default);
    }

    public static function put(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => json_encode($value)]);
        app()->forgetInstance(self::CACHE);
    }

    public static function office(): array
    {
        return [
            'name' => static::get('office_name'),
            'location' => static::get('office_location'),
            'address' => static::get('office_address'),
            'email' => static::get('reception_email'),
        ];
    }
}
