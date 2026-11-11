<?php

namespace App\Support;

/**
 * Hides private/internal IP addresses (10.x, 172.16-31.x, 192.168.x, 127.x ...)
 * when audit data is shown in the browser. Real public IPs are shown as-is.
 * The database is NOT changed; this only affects what is displayed.
 */
class IpDisplay
{
    /** Single IP for a table cell or label. */
    public static function show(?string $ip): string
    {
        if (! $ip) {
            return '—';
        }

        return self::isPrivateIp($ip) ? 'Internal' : $ip;
    }

    /** Pretty JSON for the "Metadata" box. Accepts an array or a JSON string. */
    public static function prettyJson(mixed $metadata): string
    {
        if (is_string($metadata)) {
            $decoded = json_decode($metadata, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $metadata; // not JSON, show as is
            }
            $metadata = $decoded;
        }

        return json_encode(
            self::mask($metadata),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    /** Recursively replaces private IP values with "Internal". */
    public static function mask(mixed $data): mixed
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = self::mask($value);
            }

            return $data;
        }

        if (is_string($data) && self::isPrivateIp($data)) {
            return 'Internal';
        }

        return $data;
    }

    private static function isPrivateIp(string $value): bool
    {
        // Must be a valid IP at all...
        if (filter_var($value, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        // ...and fail the "public only" check to count as private/reserved.
        return filter_var(
            $value,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }
}