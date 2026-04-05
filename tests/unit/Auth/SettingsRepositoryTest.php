<?php

namespace WSms\Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use WSms\Auth\SettingsRepository;

class SettingsRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['_test_options'] = [];
    }

    protected function tearDown(): void
    {
        $GLOBALS['_test_options'] = [];
    }

    // --- all() returns complete DEFAULTS when DB is empty ---

    public function testAllReturnsCompleteDefaultsWhenDbEmpty(): void
    {
        $repo = new SettingsRepository();
        $settings = $repo->all();

        // Every key in DEFAULTS must be present.
        foreach (SettingsRepository::DEFAULTS as $key => $default) {
            $this->assertArrayHasKey($key, $settings, "Missing key: {$key}");

            // profile_fields is populated by migration from registration_fields.
            if ($key === 'profile_fields') {
                $this->assertNotEmpty($settings['profile_fields']);
                continue;
            }

            $this->assertEquals($default, $settings[$key], "Default mismatch for key: {$key}");
        }
    }

    // --- DB overrides merge correctly while preserving other defaults ---

    public function testDbOverridesMergeWhilePreservingDefaults(): void
    {
        $GLOBALS['_test_options'][SettingsRepository::OPTION_KEY] = [
            'email' => ['enabled' => false],
        ];

        $repo = new SettingsRepository();
        $settings = $repo->all();

        // Override applied.
        $this->assertFalse($settings['email']['enabled']);

        // Other email defaults preserved.
        $this->assertEquals('login', $settings['email']['usage']);
        $this->assertEquals(['otp'], $settings['email']['verification_methods']);
        $this->assertEquals(6, $settings['email']['code_length']);
        $this->assertEquals(600, $settings['email']['expiry']);

        // Other channels untouched.
        $this->assertTrue($settings['password']['enabled']);
        $this->assertFalse($settings['phone']['enabled']);
    }

    // --- Indexed arrays replaced wholesale (not merged by index) ---

    public function testIndexedArraysReplacedWholesale(): void
    {
        $GLOBALS['_test_options'][SettingsRepository::OPTION_KEY] = [
            'captcha' => [
                'protected_actions' => ['login'],
            ],
        ];

        $repo = new SettingsRepository();
        $settings = $repo->all();

        // Admin's reduced list should NOT be expanded with defaults.
        $this->assertEquals(['login'], $settings['captcha']['protected_actions']);
    }

    public function testMfaRequiredRolesReplacedWholesale(): void
    {
        $GLOBALS['_test_options'][SettingsRepository::OPTION_KEY] = [
            'mfa_required_roles' => ['administrator'],
        ];

        $repo = new SettingsRepository();
        $settings = $repo->all();

        $this->assertEquals(['administrator'], $settings['mfa_required_roles']);
    }

    // --- Social provider nested merge ---

    public function testSocialNestedMergePreservesSiblingProviders(): void
    {
        $GLOBALS['_test_options'][SettingsRepository::OPTION_KEY] = [
            'social' => [
                'google' => ['enabled' => true, 'client_id' => 'gid', 'client_secret' => 'gsecret'],
            ],
        ];

        $repo = new SettingsRepository();
        $settings = $repo->all();

        // Google override applied.
        $this->assertTrue($settings['social']['google']['enabled']);
        $this->assertEquals('gid', $settings['social']['google']['client_id']);

        // GitHub defaults still present (not lost).
        $this->assertArrayHasKey('github', $settings['social']);
        $this->assertFalse($settings['social']['github']['enabled']);

        // Telegram defaults still present.
        $this->assertArrayHasKey('telegram', $settings['social']);
        $this->assertFalse($settings['social']['telegram']['enabled']);
    }

    public function testSocialNestedMergePreservesMissingKeys(): void
    {
        $GLOBALS['_test_options'][SettingsRepository::OPTION_KEY] = [
            'social' => [
                'google' => ['client_id' => 'myid'],
            ],
        ];

        $repo = new SettingsRepository();
        $settings = $repo->all();

        // Partial google update should preserve client_secret default.
        $this->assertEquals('myid', $settings['social']['google']['client_id']);
        $this->assertEquals('', $settings['social']['google']['client_secret']);
        $this->assertFalse($settings['social']['google']['enabled']);
    }

    // --- Top-level scalar defaults ---

    public function testTopLevelScalarDefaultsPresent(): void
    {
        $repo = new SettingsRepository();
        $settings = $repo->all();

        $this->assertEquals('voluntary', $settings['enrollment_timing']);
        $this->assertEquals(7, $settings['grace_period_days']);
        $this->assertEquals('/account', $settings['auth_base_url']);
        $this->assertFalse($settings['redirect_login']);
        $this->assertTrue($settings['enable_registration']);
        $this->assertFalse($settings['auto_create_users']);
        $this->assertEquals('standard', $settings['log_verbosity']);
        $this->assertEquals(30, $settings['log_retention_days']);
        $this->assertEquals(['email', 'password'], $settings['registration_fields']);
        // profile_fields is auto-migrated from registration_fields when empty.
        $this->assertNotEmpty($settings['profile_fields']);
        $this->assertTrue($settings['pending_user_cleanup_enabled']);
        $this->assertEquals(24, $settings['pending_user_ttl_hours']);
        $this->assertEquals('registration_only', $settings['social_profile_sync']);
        $this->assertEquals('', $settings['site_phone']);
        $this->assertEquals('sms', $settings['site_phone_channel']);
        $this->assertEquals('', $settings['terms_url']);
        $this->assertEquals('', $settings['privacy_url']);
        $this->assertEquals('', $settings['subscription_consent_text']);
        $this->assertFalse($settings['subscription_consent_required']);
    }

    // --- invalidateCache ---

    public function testInvalidateCacheReReadsFromDb(): void
    {
        $GLOBALS['_test_options'][SettingsRepository::OPTION_KEY] = [
            'log_verbosity' => 'verbose',
        ];

        $repo = new SettingsRepository();
        $this->assertEquals('verbose', $repo->all()['log_verbosity']);

        // Simulate settings change.
        $GLOBALS['_test_options'][SettingsRepository::OPTION_KEY] = [
            'log_verbosity' => 'minimal',
        ];

        // Cached: still returns old value.
        $this->assertEquals('verbose', $repo->all()['log_verbosity']);

        // After invalidation: returns fresh value.
        $repo->invalidateCache();
        $this->assertEquals('minimal', $repo->all()['log_verbosity']);
    }

    // --- channel() helper ---

    public function testChannelReturnsSubsetWithDefaults(): void
    {
        $repo = new SettingsRepository();

        $captcha = $repo->channel('captcha');
        $this->assertFalse($captcha['enabled']);
        $this->assertEquals('turnstile', $captcha['provider']);
    }

    // --- get() helper ---

    public function testGetReturnsDefaultAppliedValue(): void
    {
        $repo = new SettingsRepository();

        $this->assertEquals('voluntary', $repo->get('enrollment_timing'));
        $this->assertEquals(7, $repo->get('grace_period_days'));
    }

    public function testGetFallsBackForUnknownKey(): void
    {
        $repo = new SettingsRepository();

        $this->assertNull($repo->get('nonexistent_key'));
        $this->assertEquals('fallback', $repo->get('nonexistent_key', 'fallback'));
    }

    // --- AdminController ALLOWED lists cover every key in DEFAULTS ---

    public function testAdminControllerAllowedListsCoversAllDefaultKeys(): void
    {
        $ref = new \ReflectionClass(\WSms\Rest\AdminController::class);
        $scalarConst = $ref->getConstant('ALLOWED_SCALAR_SETTINGS');
        $channelConst = $ref->getConstant('ALLOWED_CHANNEL_KEYS');
        $allowed = array_merge($scalarConst, $channelConst);

        foreach (array_keys(SettingsRepository::DEFAULTS) as $key) {
            $this->assertContains(
                $key,
                $allowed,
                "SettingsRepository::DEFAULTS key '{$key}' is not in AdminController's ALLOWED lists"
            );
        }
    }

    // --- PHP-to-frontend sync: export DEFAULTS as JSON snapshot ---

    public function testExportDefaultsSnapshot(): void
    {
        $snapshotPath = dirname(__DIR__, 3) . '/tests/snapshots/settings-defaults.json';
        $dir = dirname($snapshotPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $snapshotPath,
            json_encode(SettingsRepository::DEFAULTS, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );

        $this->assertFileExists($snapshotPath);
    }
}
