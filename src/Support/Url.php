<?php

declare(strict_types=1);

namespace Celerik\Ui\Support;

/**
 * URLs from data, made safe for href and src.
 */
final class Url
{
    /**
     * A relative, http or https URL, or null (javascript:, data: and other schemes are dropped).
     */
    public static function safe(string $url): ?string
    {
        $url = trim($url);
        // Browsers drop tabs and line breaks from URLs: "java\tscript:" would run.
        if ($url === '' || preg_match('/[\x00-\x20\x7f]/', $url) === 1) {
            return null;
        }
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if ($scheme === false) {
            return null;
        }

        return $scheme === null || in_array(strtolower($scheme), ['http', 'https'], true) ? $url : null;
    }
}
