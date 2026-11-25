<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class SafeUrl
{
    private const BLOCKED_HOSTS = [
        'localhost',
        'localhost.localdomain',
        'metadata',
        'metadata.google.internal',
        'metadata.goog',
        'instance-data',
    ];

    /**
     * Validate outbound URL against SSRF and dangerous schemes.
     *
     * @return array{ok: bool, error: ?string}
     */
    public static function validate(string $url): array
    {
        $url = trim($url);

        if ($url === '') {
            return ['ok' => false, 'error' => 'URL kosong.'];
        }

        $parts = parse_url($url);

        if (! is_array($parts)) {
            return ['ok' => false, 'error' => 'Format URL tidak valid.'];
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return ['ok' => false, 'error' => 'Skema URL harus http atau https.'];
        }

        $rawHost = strtolower((string) ($parts['host'] ?? ''));
        $host = trim($rawHost, '[]');

        if ($host === '') {
            return ['ok' => false, 'error' => 'Host URL tidak valid.'];
        }

        if (in_array($host, self::BLOCKED_HOSTS, true) || str_ends_with($host, '.localhost')) {
            return ['ok' => false, 'error' => 'Host internal/metadata tidak diizinkan.'];
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false && self::isMetadataIp($host)) {
            return ['ok' => false, 'error' => 'Alamat metadata cloud tidak diizinkan.'];
        }

        $allowedHosts = (array) config('latekaje.safe_url_allowed_hosts', []);

        if (in_array($host, $allowedHosts, true)) {
            return ['ok' => true, 'error' => null];
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            if (! self::isPublicIp($host)) {
                return ['ok' => false, 'error' => 'Alamat IP privat/loopback/reserved diblokir (tambahkan ke SAFE_URL_ALLOWED_HOSTS bila memang internal tepercaya).'];
            }

            return ['ok' => true, 'error' => null];
        }

        $resolvedIps = self::resolveIps($host);

        foreach ($resolvedIps as $ip) {
            if (self::isMetadataIp($ip) || ! self::isPublicIp($ip)) {
                return ['ok' => false, 'error' => 'Host mengarah ke alamat IP privat/internal yang diblokir.'];
            }
        }

        return ['ok' => true, 'error' => null];
    }

    public static function isSafe(string $url): bool
    {
        return self::validate($url)['ok'];
    }

    /**
     * @throws ValidationException
     */
    public static function assertSafe(string $url, string $field = 'url'): void
    {
        $result = self::validate($url);

        if (! $result['ok']) {
            throw ValidationException::withMessages([
                $field => $result['error'] ?? 'URL tidak aman.',
            ]);
        }
    }

    private static function isMetadataIp(string $ip): bool
    {
        // IPv4 link-local / cloud metadata 169.254.0.0/16
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $long = ip2long($ip);

            return $long !== false && ($long & 0xFFFF0000) === 0xA9FE0000;
        }

        // IPv6 link-local fe80::/10
        $lower = strtolower($ip);

        return str_starts_with($lower, 'fe8')
            || str_starts_with($lower, 'fe9')
            || str_starts_with($lower, 'fea')
            || str_starts_with($lower, 'feb');
    }

    private static function isPublicIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        // Also block Carrier-Grade NAT / Tailscale 100.64.0.0/10 unless explicitly allowlisted
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $long = ip2long($ip);

            if ($long !== false && ($long & 0xFFC00000) === 0x64400000) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private static function resolveIps(string $host): array
    {
        $ips = [];

        $v4 = @gethostbynamel($host);
        if (is_array($v4)) {
            $ips = array_merge($ips, $v4);
        }

        if (function_exists('dns_get_record')) {
            $aaaa = @dns_get_record($host, DNS_AAAA);
            if (is_array($aaaa)) {
                foreach ($aaaa as $record) {
                    if (! empty($record['ipv6'])) {
                        $ips[] = (string) $record['ipv6'];
                    }
                }
            }
        }

        return array_values(array_unique($ips));
    }
}
