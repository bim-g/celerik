<?php

declare(strict_types=1);

namespace Caelrik\Ui\Support;

/**
 * URLs from data, made safe for href and src.
 */
final class Url
{
    /**
     * A relative, http or https URL, or null (javascript:, data: and other schemes are dropped).
     *
     * The scheme is read as browsers read it: the letters before the first colon, when no
     * "/", "?" or "#" comes first. PHP's parse_url() reads "javascript:0" as a host and a
     * port, so it is not used.
     */
    public static function safe(string $url): ?string
    {
        $url = trim($url);
        // Browsers drop tabs and line breaks from URLs: "java\tscript:" would run.
        if ($url === '' || preg_match('/[\x00-\x20\x7f]/', $url) === 1) {
            return null;
        }
        if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $url, $scheme) === 1) {
            return in_array(strtolower($scheme[1]), ['http', 'https'], true) ? $url : null;
        }

        return $url;
    }
}
