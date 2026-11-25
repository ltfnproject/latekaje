<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SecureImageService
{
    private const MAX_DIMENSION = 1600;

    private const ALLOWED_TYPES = [
        IMAGETYPE_JPEG,
        IMAGETYPE_PNG,
        IMAGETYPE_WEBP,
        IMAGETYPE_GIF,
    ];

    /**
     * Validate and re-encode an already stored image on the given disk,
     * stripping EXIF metadata and polyglot payloads, then deleting the raw file.
     *
     * @throws ValidationException
     */
    public static function sanitizeStoredImage(string $relativePath, string $disk = 'local', string $directory = 'returns'): string
    {
        $storage = Storage::disk($disk);

        if (! $storage->exists($relativePath)) {
            throw ValidationException::withMessages([
                'return_photo_path' => 'File foto bukti tidak ditemukan.',
            ]);
        }

        $raw = (string) $storage->get($relativePath);

        try {
            $cleanPath = self::sanitizeBytesAndStore($raw, $disk, $directory);
        } finally {
            if ($storage->exists($relativePath)) {
                $storage->delete($relativePath);
            }
        }

        return $cleanPath;
    }

    /**
     * Validate raw image bytes, resize if larger than MAX_DIMENSION, re-encode to clean JPEG,
     * and store in private storage under `<directory>/YYYY/MM/<uuid>.jpg`.
     *
     * @throws ValidationException
     */
    public static function sanitizeBytesAndStore(string $rawBytes, string $disk = 'local', string $directory = 'returns'): string
    {
        $info = @getimagesizefromstring($rawBytes);

        if (! is_array($info) || empty($info[0]) || empty($info[1]) || ! in_array($info[2] ?? null, self::ALLOWED_TYPES, true)) {
            throw ValidationException::withMessages([
                'return_photo_path' => 'File foto bukti tidak valid. Gunakan gambar JPG, PNG, atau WEBP.',
            ]);
        }

        $src = @imagecreatefromstring($rawBytes);

        if ($src === false) {
            throw ValidationException::withMessages([
                'return_photo_path' => 'Gambar foto bukti rusak atau tidak dapat diproses.',
            ]);
        }

        $srcWidth = imagesx($src);
        $srcHeight = imagesy($src);

        $scale = min(1.0, self::MAX_DIMENSION / max($srcWidth, $srcHeight));
        $dstWidth = max(1, (int) round($srcWidth * $scale));
        $dstHeight = max(1, (int) round($srcHeight * $scale));

        $dst = imagecreatetruecolor($dstWidth, $dstHeight);
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefilledrectangle($dst, 0, 0, $dstWidth, $dstHeight, $white);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstWidth, $dstHeight, $srcWidth, $srcHeight);

        ob_start();
        $encoded = imagejpeg($dst, null, 82);
        $cleanBytes = ob_get_clean();

        imagedestroy($src);
        imagedestroy($dst);

        if (! $encoded || ! is_string($cleanBytes) || $cleanBytes === '') {
            throw ValidationException::withMessages([
                'return_photo_path' => 'Gagal memproses ulang gambar foto bukti.',
            ]);
        }

        $subDir = trim($directory, '/').'/'.now()->format('Y/m');
        $cleanPath = $subDir.'/'.Str::uuid()->toString().'.jpg';

        Storage::disk($disk)->put($cleanPath, $cleanBytes);

        return $cleanPath;
    }
}
