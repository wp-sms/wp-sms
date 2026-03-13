<?php

namespace WSms\Tests\Unit\Social;

use PHPUnit\Framework\TestCase;
use WSms\Social\SocialAccountRepository;

class SocialAccountRepositoryTest extends TestCase
{
    private SocialAccountRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new SocialAccountRepository();
    }

    public function testEncryptDecryptRoundTrip(): void
    {
        $plaintext = '{"access_token":"ya29.abc","refresh_token":"1//xyz"}';

        // Use reflection to test the private encrypt/decrypt methods.
        $encrypt = new \ReflectionMethod($this->repository, 'encryptValue');
        $decrypt = new \ReflectionMethod($this->repository, 'decryptValue');

        $ciphertext = $encrypt->invoke($this->repository, $plaintext);

        $this->assertNotSame($plaintext, $ciphertext);
        $this->assertSame($plaintext, $decrypt->invoke($this->repository, $ciphertext));
    }

    public function testDecryptInvalidDataReturnsEmpty(): void
    {
        $result = $this->repository->decryptValue('not-valid-base64!');

        $this->assertSame('', $result);
    }

    public function testDecryptTooShortDataReturnsEmpty(): void
    {
        $result = $this->repository->decryptValue(base64_encode('short'));

        $this->assertSame('', $result);
    }
}
