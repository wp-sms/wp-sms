<?php

namespace WSms\Mfa\Support;

class EmailMasker
{
    /**
     * Mask an email address for display.
     *
     * navid@gmail.com → n***@gmail.com
     */
    public static function mask(string $email): string
    {
        $parts = explode('@', $email, 2);

        if (count($parts) !== 2) {
            return $email;
        }

        [$local, $domain] = $parts;

        $localLen = mb_strlen($local);

        if ($localLen <= 1) {
            return $local . '***@' . $domain;
        }

        return mb_substr($local, 0, 1)
            . str_repeat('*', min($localLen - 1, 3))
            . '@' . $domain;
    }
}
