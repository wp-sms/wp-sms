<?php

namespace WSms\Mfa;

defined('ABSPATH') || exit;

class SecretEncryptor
{
    private const PREFIX = 'enc1::';

    public function encrypt(string $plaintext, int $userId): string
    {
        $key = $this->getKey();

        if ($key === null) {
            return $plaintext;
        }

        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $aad = (string) $userId;

        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $plaintext,
            $aad,
            $nonce,
            $key,
        );

        sodium_memzero($key);

        return self::PREFIX . bin2hex($nonce) . ':' . bin2hex($ciphertext);
    }

    public function decrypt(string $stored, int $userId): string
    {
        if (!str_starts_with($stored, self::PREFIX)) {
            return $stored;
        }

        $payload = substr($stored, strlen(self::PREFIX));
        $parts = explode(':', $payload, 2);

        if (count($parts) !== 2) {
            throw new \RuntimeException('Malformed encrypted secret.');
        }

        try {
            $nonce = @hex2bin($parts[0]);
            $ciphertext = @hex2bin($parts[1]);
        } catch (\ValueError) {
            throw new \RuntimeException('Malformed encrypted secret.');
        }

        if ($nonce === false || $ciphertext === false) {
            throw new \RuntimeException('Malformed encrypted secret.');
        }

        $key = $this->getKey();

        if ($key === null) {
            throw new \RuntimeException('Failed to decrypt TOTP secret. Check encryption key.');
        }

        $aad = (string) $userId;

        $result = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            $ciphertext,
            $aad,
            $nonce,
            $key,
        );

        sodium_memzero($key);

        if ($result === false) {
            throw new \RuntimeException('Failed to decrypt TOTP secret. Check encryption key.');
        }

        return $result;
    }

    private function getKey(): ?string
    {
        if (!defined('WSMS_MFA_ENCRYPTION_KEY')) {
            return null;
        }

        return sodium_crypto_generichash(WSMS_MFA_ENCRYPTION_KEY, '', SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES);
    }
}
