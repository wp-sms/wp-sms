<?php

namespace WSms\Mfa;

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
     */
    public function verify(string $code, string $hash): bool
    {
        return hash_equals($hash, $this->hash($code));
    }
}
