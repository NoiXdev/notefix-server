<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class InstanceConfig
{
    /** Keys whose values are encrypted at rest. */
    public const SECRET_KEYS = ['mail.smtp.password', 'mail.postal.key', 'storage.s3.secret'];

    public static function get(string $key, ?string $default = null): ?string
    {
        $raw = Setting::get($key);
        if ($raw === null) {
            return $default;
        }
        if (in_array($key, self::SECRET_KEYS, true)) {
            try {
                return Crypt::decryptString($raw);
            } catch (DecryptException) {
                return $default;
            }
        }

        return $raw;
    }

    public static function set(string $key, ?string $value): void
    {
        if (in_array($key, self::SECRET_KEYS, true)) {
            // Blank secret = keep the existing one.
            if ($value === null || $value === '') {
                return;
            }
            Setting::put($key, Crypt::encryptString($value));

            return;
        }

        Setting::put($key, $value);
    }

    /** Push mail settings into runtime config — only keys that are actually set. */
    public static function applyMail(): void
    {
        $map = [
            'mail.transport' => 'mail.default',
            'mail.from_address' => 'mail.from.address',
            'mail.from_name' => 'mail.from.name',
            'mail.smtp.host' => 'mail.mailers.smtp.host',
            'mail.smtp.port' => 'mail.mailers.smtp.port',
            'mail.smtp.username' => 'mail.mailers.smtp.username',
            'mail.smtp.password' => 'mail.mailers.smtp.password',
            'mail.smtp.encryption' => 'mail.mailers.smtp.encryption',
            'mail.postal.endpoint' => 'mail.mailers.postal.endpoint',
            'mail.postal.key' => 'mail.mailers.postal.key',
        ];

        foreach ($map as $settingKey => $configKey) {
            $value = self::get($settingKey);
            if ($value !== null && $value !== '') {
                config([$configKey => $value]);
            }
        }
    }

    /** The disk note images are read from / written to. */
    public static function storageDisk(): string
    {
        return self::get('storage.disk', 'local') ?? 'local';
    }

    /** Push S3 storage settings into the `s3` disk config — only keys that are set. */
    public static function applyFilesystem(): void
    {
        $map = [
            'storage.s3.key' => 'filesystems.disks.s3.key',
            'storage.s3.secret' => 'filesystems.disks.s3.secret',
            'storage.s3.region' => 'filesystems.disks.s3.region',
            'storage.s3.bucket' => 'filesystems.disks.s3.bucket',
            'storage.s3.endpoint' => 'filesystems.disks.s3.endpoint',
        ];

        foreach ($map as $settingKey => $configKey) {
            $value = self::get($settingKey);
            if ($value !== null && $value !== '') {
                config([$configKey => $value]);
            }
        }

        $pathStyle = self::get('storage.s3.path_style');
        if ($pathStyle !== null && $pathStyle !== '') {
            config(['filesystems.disks.s3.use_path_style_endpoint' => $pathStyle === 'true']);
        }
    }
}
