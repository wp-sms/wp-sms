<?php

namespace WSms\PhoneRestriction;

defined('ABSPATH') || exit;

class EnhancedPhoneDatabase
{
    private static bool $loadAttempted = false;
    private static ?self $instance = null;

    private array $data;
    private string $version;
    private ?array $byCallingCode = null;

    private function __construct(array $data, string $version)
    {
        $this->data    = $data;
        $this->version = $version;
    }

    /**
     * Get the canonical file path for the enhanced database.
     */
    public static function getFilePath(): string
    {
        return wp_upload_dir()['basedir'] . '/wsms-data/enhanced-phone-db.json';
    }

    /**
     * Load the enhanced database from file.
     * Returns null if not installed or invalid.
     */
    public static function load(): ?self
    {
        if (self::$loadAttempted) {
            return self::$instance;
        }

        self::$loadAttempted = true;

        $path = self::getFilePath();

        if (!file_exists($path)) {
            return null;
        }

        $raw = file_get_contents($path);

        if ($raw === false || $raw === '') {
            return null;
        }

        $data = json_decode($raw, true);

        if (!is_array($data) || empty($data['territories'])) {
            return null;
        }

        $version = $data['version'] ?? 'unknown';

        self::$instance = new self($data, $version);

        return self::$instance;
    }

    /**
     * Get the status of the enhanced database.
     *
     * @return array{installed: bool, version: string|null, updated_at: string|null}
     */
    public static function getStatus(): array
    {
        $db = self::load();

        if ($db === null) {
            return ['installed' => false, 'version' => null, 'updated_at' => null];
        }

        $path = self::getFilePath();

        return [
            'installed'  => true,
            'version'    => $db->getVersion(),
            'updated_at' => gmdate('c', filemtime($path)),
        ];
    }

    /** @internal For testing only */
    public static function resetCache(): void
    {
        self::$loadAttempted = false;
        self::$instance      = null;
    }

    /**
     * Disambiguate a shared country code using leading_digits patterns.
     */
    public function disambiguateCountry(string $callingCode, string $subscriberNumber): ?string
    {
        $territories = $this->getTerritoriesByCallingCode($callingCode);

        foreach ($territories as $isoCode => $territory) {
            $leadingDigits = $territory['leading_digits'] ?? null;

            if ($leadingDigits === null) {
                continue;
            }

            try {
                if (preg_match('/^' . $leadingDigits . '/', $subscriberNumber)) {
                    return $isoCode;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    /**
     * Detect the number type for a subscriber number within a country.
     *
     * @return string|null The matched type (premium_rate, toll_free, mobile, etc.) or null
     */
    public function detectNumberType(string $country, string $subscriberNumber): ?string
    {
        $territory = $this->data['territories'][$country] ?? null;

        if ($territory === null) {
            return null;
        }

        $patterns = $territory['patterns'] ?? [];

        // Check dangerous types first for early detection
        $priorityOrder = [
            'premium_rate',
            'toll_free',
            'shared_cost',
            'mobile',
            'fixed_line',
            'voip',
            'personal_number',
            'pager',
            'uan',
            'voicemail',
        ];

        foreach ($priorityOrder as $type) {
            $pattern = $patterns[$type] ?? null;

            if ($pattern === null) {
                continue;
            }

            try {
                if (preg_match('/' . $pattern . '/', $subscriberNumber)) {
                    return $type;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @return array<string, array> Territories indexed by ISO code for a given calling code
     */
    private function getTerritoriesByCallingCode(string $callingCode): array
    {
        if ($this->byCallingCode === null) {
            $this->byCallingCode = [];

            foreach ($this->data['territories'] as $isoCode => $territory) {
                $cc = $territory['country_code'] ?? '';

                if ($cc !== '') {
                    $this->byCallingCode[$cc][$isoCode] = $territory;
                }
            }
        }

        return $this->byCallingCode[$callingCode] ?? [];
    }
}
