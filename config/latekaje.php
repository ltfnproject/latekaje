<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Generic Kiosk Fallback (Fail-Closed by Default)
    |--------------------------------------------------------------------------
    | When false (recommended for LATEKAJE v4), borrower accounts (`role=users`)
    | without `code` or `member_id` cannot view any loans instead of failing open.
    */
    'allow_generic_kiosk' => filter_var(env('ALLOW_GENERIC_KIOSK', false), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Trusted Reverse Proxies
    |--------------------------------------------------------------------------
    | Comma-separated IPs/CIDRs (e.g. "127.0.0.1,172.16.0.0/12" for Docker NPM).
    | Avoid "*" unless explicitly required behind a trusted edge proxy.
    */
    'trusted_proxies' => env('TRUSTED_PROXIES', '127.0.0.1,172.16.0.0/12'),

    /*
    |--------------------------------------------------------------------------
    | Allowed Internal Outbound Hosts (SSRF Allowlist)
    |--------------------------------------------------------------------------
    | Comma-separated hostnames or IPs explicitly permitted for GOWA/Webhook
    | (e.g. internal Docker container name or Tailscale relay IP). Cloud metadata
    | addresses (169.254.0.0/16) are ALWAYS blocked regardless of this list.
    */
    'safe_url_allowed_hosts' => array_values(array_filter(array_map(
        static fn (string $host): string => strtolower(trim($host)),
        explode(',', (string) env('SAFE_URL_ALLOWED_HOSTS', ''))
    ))),
];
