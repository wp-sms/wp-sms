<?php

namespace WSms\Support;

defined('ABSPATH') || exit;

class DeviceResolver
{
    /**
     * Browser detection rules — order matters (specific forks before generic).
     * Each entry: [tokens to match (any), result name].
     */
    private const BROWSERS = [
        [['FBAN', 'FBAV'], 'Facebook'],
        [['Instagram'], 'Instagram'],
        [['FxiOS'], 'Firefox'],
        [['EdgiOS'], 'Edge'],
        [['CriOS'], 'Chrome'],
        [['Edg/', 'EdgA/'], 'Edge'],
        [['OPR/', 'OPT/'], 'Opera'],
        [['SamsungBrowser'], 'Samsung Internet'],
        [['YaBrowser'], 'Yandex'],
        [['Vivaldi'], 'Vivaldi'],
        [['UCBrowser'], 'UC Browser'],
        [['Chrome'], 'Chrome'],
        [['Firefox'], 'Firefox'],
        [['Trident', 'MSIE'], 'IE'],
    ];

    /**
     * OS detection rules — order matters (specific before generic).
     * Each entry: [tokens to match (any), result name].
     */
    private const OS_MAP = [
        [['iPhone', 'iPad', 'iPod'], 'iOS'],
        [['Windows Phone'], 'Windows Phone'],
        [['Android'], 'Android'],
        [['CrOS'], 'Chrome OS'],
        [['Windows'], 'Windows'],
        [['Macintosh', 'Mac OS X'], 'macOS'],
        [['Linux'], 'Linux'],
    ];

    /**
     * Resolve a human-readable device description from the User-Agent header.
     *
     * Returns e.g. "Chrome on Windows", "Safari on iOS", or null if
     * the UA is missing or unrecognizable.
     */
    public static function resolve(): ?string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

        if ($ua === null || $ua === '') {
            return null;
        }

        $browser = self::detectBrowser($ua);
        $os = self::matchFirst($ua, self::OS_MAP);

        if ($browser === null && $os === null) {
            return null;
        }

        if ($browser !== null && $os !== null) {
            return "$browser on $os";
        }

        return $browser ?? $os;
    }

    private static function detectBrowser(string $ua): ?string
    {
        $match = self::matchFirst($ua, self::BROWSERS);

        if ($match !== null) {
            return $match;
        }

        // Safari requires both tokens and no Chromium fork match
        if (str_contains($ua, 'Safari') && str_contains($ua, 'Version/')) {
            return 'Safari';
        }

        return null;
    }

    /**
     * Return the name from the first rule where any token matches.
     *
     * @param list<array{list<string>, string}> $rules
     */
    private static function matchFirst(string $ua, array $rules): ?string
    {
        foreach ($rules as [$tokens, $name]) {
            foreach ($tokens as $token) {
                if (str_contains($ua, $token)) {
                    return $name;
                }
            }
        }

        return null;
    }
}
