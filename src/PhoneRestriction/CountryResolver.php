<?php

namespace WSms\PhoneRestriction;

defined('ABSPATH') || exit;

class CountryResolver
{
    /** @var array{prefixes: array<string, array{countries: string[], primary: string|null}>, country_names: array<string, string>}|null */
    private static ?array $prefixData = null;

    private ?EnhancedPhoneDatabase $enhancedDb;

    public function __construct(?EnhancedPhoneDatabase $enhancedDb = null)
    {
        $this->enhancedDb = $enhancedDb;
    }

    /**
     * Resolve country and number type in a single pass.
     *
     * @return array{country: string|null, number_type: string|null}
     */
    public function resolve(string $e164Phone): array
    {
        $digits = $this->extractDigits($e164Phone);

        if ($digits === null) {
            return ['country' => null, 'number_type' => null];
        }

        $prefixData = $this->loadPrefixData();
        $match      = $this->matchPrefix($digits, $prefixData['prefixes']);

        if ($match === null) {
            return ['country' => null, 'number_type' => null];
        }

        [$callingCode, $info] = $match;
        $subscriberNumber = substr($digits, strlen($callingCode));

        // Resolve country
        if (count($info['countries']) === 1) {
            $country = $info['countries'][0];
        } elseif ($this->enhancedDb !== null) {
            $country = $this->enhancedDb->disambiguateCountry($callingCode, $subscriberNumber) ?? $info['primary'];
        } else {
            $country = $info['primary'];
        }

        // Detect number type (requires enhanced DB + resolved country)
        $numberType = null;
        if ($country !== null && $this->enhancedDb !== null) {
            $numberType = $this->enhancedDb->detectNumberType($country, $subscriberNumber);
        }

        return ['country' => $country, 'number_type' => $numberType];
    }

    /**
     * Resolve ISO country code only.
     */
    public function resolveCountry(string $e164Phone): ?string
    {
        return $this->resolve($e164Phone)['country'];
    }

    /**
     * Get number type only. Returns null if enhanced DB is not available.
     */
    public function getNumberType(string $e164Phone): ?string
    {
        return $this->resolve($e164Phone)['number_type'];
    }

    /**
     * Get all countries as ISO code => name map.
     */
    public function getAllCountries(): array
    {
        return $this->loadPrefixData()['country_names'];
    }

    /** @internal For testing only */
    public static function resetCache(): void
    {
        self::$prefixData = null;
    }

    private function extractDigits(string $e164Phone): ?string
    {
        $phone = ltrim($e164Phone, '+');

        if ($phone === '' || !ctype_digit($phone)) {
            return null;
        }

        return $phone;
    }

    /**
     * @return array{0: string, 1: array{countries: string[], primary: string|null}}|null
     */
    private function matchPrefix(string $digits, array $prefixes): ?array
    {
        for ($len = min(3, strlen($digits)); $len >= 1; $len--) {
            $prefix = substr($digits, 0, $len);

            if (isset($prefixes[$prefix])) {
                return [$prefix, $prefixes[$prefix]];
            }
        }

        return null;
    }

    private function loadPrefixData(): array
    {
        if (self::$prefixData === null) {
            $path = dirname(__DIR__, 2) . '/data/phone-country-prefixes.json';
            $json = file_get_contents($path);

            self::$prefixData = json_decode($json, true) ?: ['prefixes' => [], 'country_names' => []];
        }

        return self::$prefixData;
    }
}
