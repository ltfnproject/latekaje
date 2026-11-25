<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    public const ENCRYPTED_KEYS = [
        'gowa_pass',
        'gowa_relay_secret',
        'webhook_secret',
        'webhook_pass',
    ];

    private const ENCRYPTED_PREFIX = 'enc::';

    protected $fillable = ['key', 'value'];

    public static function isEncryptedKey(string $key): bool
    {
        return in_array($key, self::ENCRYPTED_KEYS, true);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $raw = Cache::remember(
            'setting:'.$key,
            3600,
            fn () => static::where('key', $key)->value('value')
        );

        if ($raw === null) {
            return $default;
        }

        if (self::isEncryptedKey($key) && is_string($raw) && str_starts_with($raw, self::ENCRYPTED_PREFIX)) {
            try {
                return Crypt::decryptString(substr($raw, strlen(self::ENCRYPTED_PREFIX)));
            } catch (DecryptException) {
                return $default;
            }
        }

        return $raw;
    }

    public static function set(string $key, mixed $value): void
    {
        $stored = $value === null ? null : (string) $value;

        if ($stored !== null && $stored !== '' && self::isEncryptedKey($key)) {
            $stored = self::ENCRYPTED_PREFIX.Crypt::encryptString($stored);
        }

        static::updateOrCreate(['key' => $key], ['value' => $stored]);
        Cache::forget('setting:'.$key);
    }

    public static function boolean(string $key, bool $default = false): bool
    {
        return filter_var(static::get($key, $default), FILTER_VALIDATE_BOOLEAN);
    }
}
