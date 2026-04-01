<?php

namespace WSms\Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use WSms\Auth\SettingsRepository;
use WSms\Auth\TrustedDeviceManager;

class TrustedDeviceManagerTest extends TestCase
{
    private TrustedDeviceManager $manager;

    protected function setUp(): void
    {
        $GLOBALS['_test_options'] = [];
        $GLOBALS['_test_user_meta'] = [];
        $_COOKIE = [];

        $this->enableTrustedDevices();

        $this->manager = new TrustedDeviceManager(new SettingsRepository());
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['_test_options'],
            $GLOBALS['_test_user_meta'],
        );
        $_COOKIE = [];
    }

    public function testTrustStoresTokenHashInUserMeta(): void
    {
        $this->manager->trust(1);

        $raw = get_user_meta(1, 'wsms_trusted_devices', true);
        $devices = json_decode($raw, true);

        $this->assertIsArray($devices);
        $this->assertCount(1, $devices);
        $this->assertArrayHasKey('token_hash', $devices[0]);
        $this->assertArrayHasKey('expires_at', $devices[0]);
        $this->assertArrayHasKey('created_at', $devices[0]);
    }

    public function testTrustExpiresAtMatchesTtl(): void
    {
        $this->manager->trust(1);

        $raw = get_user_meta(1, 'wsms_trusted_devices', true);
        $devices = json_decode($raw, true);

        $expectedExpiry = time() + 2592000; // default 30 days
        $this->assertEqualsWithDelta($expectedExpiry, $devices[0]['expires_at'], 5);
    }

    public function testIsTrustedReturnsFalseWithNoCookie(): void
    {
        $this->assertFalse($this->manager->isTrusted(1));
    }

    public function testIsTrustedReturnsFalseWhenDisabled(): void
    {
        $this->disableTrustedDevices();
        $manager = new TrustedDeviceManager(new SettingsRepository());

        $this->assertFalse($manager->isTrusted(1));
    }

    public function testIsTrustedReturnsFalseWithTamperedCookie(): void
    {
        $_COOKIE['wsms_td'] = base64_encode('1|faketoken|9999999999|badsignature');

        $this->assertFalse($this->manager->isTrusted(1));
    }

    public function testIsTrustedReturnsFalseForWrongUser(): void
    {
        // Simulate a valid cookie for user 1.
        $this->simulateTrustCookie(1);

        // Check trust for user 2 should fail (user_id mismatch).
        $this->assertFalse($this->manager->isTrusted(2));
    }

    public function testIsTrustedReturnsTrueWithValidCookie(): void
    {
        $this->simulateTrustCookie(1);

        $this->assertTrue($this->manager->isTrusted(1));
    }

    public function testIsTrustedRotatesToken(): void
    {
        $this->simulateTrustCookie(1);

        // Before: 1 device.
        $raw = get_user_meta(1, 'wsms_trusted_devices', true);
        $before = json_decode($raw, true);
        $this->assertCount(1, $before);
        $oldHash = $before[0]['token_hash'];

        // isTrusted should rotate the token.
        $this->manager->isTrusted(1);

        // After: still 1 device, but with a different hash (old removed, new added).
        $raw = get_user_meta(1, 'wsms_trusted_devices', true);
        $after = json_decode($raw, true);
        $this->assertCount(1, $after);
        $this->assertNotSame($oldHash, $after[0]['token_hash']);
    }

    public function testIsTrustedReturnsFalseWhenTokenNotInMeta(): void
    {
        // Set a valid-looking cookie but with no matching hash in meta.
        $this->simulateTrustCookie(1);

        // Clear the user meta so the hash doesn't match.
        update_user_meta(1, 'wsms_trusted_devices', json_encode([]));

        $this->assertFalse($this->manager->isTrusted(1));
    }

    public function testRevokeAllClearsMeta(): void
    {
        $this->manager->trust(1);

        $this->assertNotEmpty(get_user_meta(1, 'wsms_trusted_devices', true));

        $this->manager->revokeAll(1);

        $this->assertEmpty(get_user_meta(1, 'wsms_trusted_devices', true));
    }

    public function testMaxDevicesEnforced(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->manager->trust(1);
        }

        $raw = get_user_meta(1, 'wsms_trusted_devices', true);
        $devices = json_decode($raw, true);

        $this->assertCount(5, $devices, 'Should enforce max 5 devices.');
    }

    public function testExpiredDevicesArePruned(): void
    {
        // Manually insert an expired device.
        $devices = [
            [
                'token_hash' => 'expired-hash',
                'expires_at' => time() - 3600,
                'created_at' => time() - 86400,
            ],
        ];
        update_user_meta(1, 'wsms_trusted_devices', json_encode($devices));

        // Trust again — should prune the expired entry.
        $this->manager->trust(1);

        $raw = get_user_meta(1, 'wsms_trusted_devices', true);
        $stored = json_decode($raw, true);

        $this->assertCount(1, $stored, 'Expired device should be pruned.');
        $this->assertNotSame('expired-hash', $stored[0]['token_hash']);
    }

    public function testIsTrustedReturnsFalseWithInvalidBase64Cookie(): void
    {
        $_COOKIE['wsms_td'] = '%%%not-base64%%%';

        $this->assertFalse($this->manager->isTrusted(1));
    }

    public function testIsTrustedReturnsFalseWithEmptyCookie(): void
    {
        $_COOKIE['wsms_td'] = '';

        $this->assertFalse($this->manager->isTrusted(1));
    }

    public function testIsTrustedReturnsFalseWithWrongPartCount(): void
    {
        $_COOKIE['wsms_td'] = base64_encode('only|two');

        $this->assertFalse($this->manager->isTrusted(1));
    }

    public function testTrustWithCustomTtl(): void
    {
        $this->enableTrustedDevices(7200); // 2 hours
        $manager = new TrustedDeviceManager(new SettingsRepository());

        $manager->trust(1);

        $raw = get_user_meta(1, 'wsms_trusted_devices', true);
        $devices = json_decode($raw, true);

        $expectedExpiry = time() + 7200;
        $this->assertEqualsWithDelta($expectedExpiry, $devices[0]['expires_at'], 5);
    }

    // -- Helpers --

    private function enableTrustedDevices(int $ttl = 2592000): void
    {
        $GLOBALS['_test_options'][SettingsRepository::OPTION_KEY] = [
            'trusted_devices' => [
                'enabled' => true,
                'ttl'     => $ttl,
            ],
        ];
    }

    private function disableTrustedDevices(): void
    {
        $GLOBALS['_test_options'][SettingsRepository::OPTION_KEY] = [
            'trusted_devices' => [
                'enabled' => false,
            ],
        ];
    }

    /**
     * Simulate a trusted device by calling trust() and then constructing
     * the cookie value from the stored meta, since setcookie() doesn't
     * populate $_COOKIE in the same process.
     */
    private function simulateTrustCookie(int $userId): void
    {
        // Generate the token and store it.
        $token = bin2hex(random_bytes(32));
        $ttl = 2592000;
        $expiresAt = time() + $ttl;

        // Build cookie the same way TrustedDeviceManager::trust() does.
        $payload = $userId . '|' . $token . '|' . $expiresAt;
        $signature = hash_hmac('sha256', $payload, \WSms\Support\SigningKey::get());
        $cookieValue = base64_encode($payload . '|' . $signature);

        // Set the cookie superglobal manually.
        $_COOKIE['wsms_td'] = $cookieValue;

        // Store the hashed token in user meta.
        $tokenHash = hash_hmac('sha256', $token, \WSms\Support\SigningKey::get());
        $devices = [
            [
                'token_hash' => $tokenHash,
                'expires_at' => $expiresAt,
                'created_at' => time(),
            ],
        ];
        update_user_meta($userId, 'wsms_trusted_devices', json_encode($devices));
    }
}
