<?php

namespace WSms\Tests\Unit\Mfa;

use PHPUnit\Framework\TestCase;
use WSms\Mfa\SecretEncryptor;

class SecretEncryptorTest extends TestCase
{
    private SecretEncryptor $encryptor;

    protected function setUp(): void
    {
        $this->encryptor = new SecretEncryptor();
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testPlaintextPassthroughWhenNoKeyDefined(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';

        $encrypted = $this->encryptor->encrypt($secret, 1);

        $this->assertSame($secret, $encrypted);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testDecryptReturnsPlaintextWhenNotEncrypted(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';

        $decrypted = $this->encryptor->decrypt($secret, 1);

        $this->assertSame($secret, $decrypted);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testEncryptDecryptRoundTrip(): void
    {
        define('WSMS_MFA_ENCRYPTION_KEY', 'my-super-secret-encryption-key');

        $secret = 'JBSWY3DPEHPK3PXP';

        $encrypted = $this->encryptor->encrypt($secret, 42);

        $this->assertStringStartsWith('enc1::', $encrypted);
        $this->assertNotSame($secret, $encrypted);

        $decrypted = $this->encryptor->decrypt($encrypted, 42);
        $this->assertSame($secret, $decrypted);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testEncryptProducesDifferentCiphertextEachTime(): void
    {
        define('WSMS_MFA_ENCRYPTION_KEY', 'my-super-secret-encryption-key');

        $secret = 'JBSWY3DPEHPK3PXP';

        $encrypted1 = $this->encryptor->encrypt($secret, 1);
        $encrypted2 = $this->encryptor->encrypt($secret, 1);

        $this->assertNotSame($encrypted1, $encrypted2);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testDecryptFailsWithWrongUserId(): void
    {
        define('WSMS_MFA_ENCRYPTION_KEY', 'my-super-secret-encryption-key');

        $encrypted = $this->encryptor->encrypt('JBSWY3DPEHPK3PXP', 1);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to decrypt');

        $this->encryptor->decrypt($encrypted, 999);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testDecryptFailsWithWrongKey(): void
    {
        define('WSMS_MFA_ENCRYPTION_KEY', 'wrong-key');

        $secret = 'JBSWY3DPEHPK3PXP';
        $correctKey = sodium_crypto_generichash('correct-key', '', SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES);
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($secret, '1', $nonce, $correctKey);
        $encrypted = 'enc1::' . bin2hex($nonce) . ':' . bin2hex($ciphertext);

        $this->expectException(\RuntimeException::class);

        $this->encryptor->decrypt($encrypted, 1);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testDecryptFailsWithMalformedData(): void
    {
        define('WSMS_MFA_ENCRYPTION_KEY', 'my-key');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Malformed encrypted secret');

        $this->encryptor->decrypt('enc1::not-a-valid-format', 1);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testDecryptFailsWithMalformedHex(): void
    {
        define('WSMS_MFA_ENCRYPTION_KEY', 'my-key');

        $this->expectException(\RuntimeException::class);

        $this->encryptor->decrypt('enc1::zzzz:zzzz', 1);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testDecryptFailsWhenKeyNotDefinedButDataIsEncrypted(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to decrypt');

        $this->encryptor->decrypt('enc1::aabb:ccdd', 1);
    }
}
