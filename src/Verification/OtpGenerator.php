<?php

namespace WSms\Verification;

class OtpGenerator
{
    /**
     * Generate a numeric OTP code.
     */
    public function generate(int $length = 6): string
    {
        $max = (int) str_pad('', $length, '9');

        return str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
    }

    /**
     * Generate a hex-encoded random token.
     */
    public function generateToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    /**
     * Hash a code using SHA-256.
     */
    public function hash(string $code): string
    {
        return hash('sha256', $code);
    }

    /**
     * Verify a code against a hash using constant-time comparison.
     * Normalizes Unicode digits to ASCII before hashing.
     */
    public function verify(string $code, string $hash): bool
    {
        return hash_equals($hash, $this->hash(self::normalizeDigits($code)));
    }

    /**
     * Replace Unicode decimal digits with their ASCII 0-9 equivalents.
     *
     * Supports 18 digit blocks: Arabic-Indic, Extended Arabic-Indic (Persian/Urdu),
     * Devanagari, Bengali, Gurmukhi, Gujarati, Oriya, Tamil, Telugu, Kannada,
     * Malayalam, Thai, Lao, Tibetan, Myanmar, Khmer, Mongolian, Full-width.
     */
    public static function normalizeDigits(string $input): string
    {
        // Fast path: already ASCII digits only
        if (preg_match('/^[0-9]*$/', $input)) {
            return $input;
        }

        // Zero code-points for each supported digit block
        static $zeros = [
            0x0660, // Arabic-Indic
            0x06F0, // Extended Arabic-Indic (Persian/Urdu)
            0x0966, // Devanagari
            0x09E6, // Bengali
            0x0A66, // Gurmukhi
            0x0AE6, // Gujarati
            0x0B66, // Oriya
            0x0BE6, // Tamil
            0x0C66, // Telugu
            0x0CE6, // Kannada
            0x0D66, // Malayalam
            0x0E50, // Thai
            0x0ED0, // Lao
            0x0F20, // Tibetan
            0x1040, // Myanmar
            0x17E0, // Khmer
            0x1810, // Mongolian
            0xFF10, // Full-width
        ];

        static $map = null;
        if ($map === null) {
            $map = [];
            foreach ($zeros as $zero) {
                for ($d = 0; $d <= 9; $d++) {
                    $map[mb_chr($zero + $d, 'UTF-8')] = (string) $d;
                }
            }
        }

        return strtr($input, $map);
    }
}
