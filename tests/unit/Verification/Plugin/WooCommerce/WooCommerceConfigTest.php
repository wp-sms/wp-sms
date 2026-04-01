<?php

namespace WSms\Tests\Unit\Verification\Plugin\WooCommerce;

use PHPUnit\Framework\TestCase;
use WSms\Auth\SettingsRepository;
use WSms\Verification\Plugin\WooCommerce\WooCommerceConfig;
use WSms\Verification\VerificationConfig;

class WooCommerceConfigTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['_test_options'] = [];
        $GLOBALS['_test_user_meta'] = [];
        $GLOBALS['_test_current_user_id'] = 0;
    }

    public function testAllDisabledByDefault(): void
    {
        $config = new WooCommerceConfig(new VerificationConfig(), new SettingsRepository());

        $this->assertFalse($config->isCheckoutEmailEnabled());
        $this->assertFalse($config->isCheckoutPhoneEnabled());
        $this->assertFalse($config->hasAnyCheckoutEnabled());
        $this->assertTrue($config->shouldSkipVerifiedUsers());
    }

    public function testCheckoutEmailEnabledWhenBothToggleAndChannelEnabled(): void
    {
        $GLOBALS['_test_options']['wsms_verification_settings'] = ['email' => ['enabled' => true]];
        $GLOBALS['_test_options'][SettingsRepository::OPTION_KEY] = ['woocommerce' => ['verify_email_at_checkout' => true]];

        $config = new WooCommerceConfig(new VerificationConfig(), new SettingsRepository());

        $this->assertTrue($config->isCheckoutEmailEnabled());
    }

    public function testCheckoutEmailDisabledWhenChannelDisabled(): void
    {
        $GLOBALS['_test_options']['wsms_verification_settings'] = ['email' => ['enabled' => false]];
        $GLOBALS['_test_options'][SettingsRepository::OPTION_KEY] = ['woocommerce' => ['verify_email_at_checkout' => true]];

        $config = new WooCommerceConfig(new VerificationConfig(), new SettingsRepository());

        $this->assertFalse($config->isCheckoutEmailEnabled());
    }

    public function testCheckoutPhoneEnabled(): void
    {
        $GLOBALS['_test_options']['wsms_verification_settings'] = ['phone' => ['enabled' => true]];
        $GLOBALS['_test_options'][SettingsRepository::OPTION_KEY] = ['woocommerce' => ['verify_phone_at_checkout' => true]];

        $config = new WooCommerceConfig(new VerificationConfig(), new SettingsRepository());

        $this->assertTrue($config->isCheckoutPhoneEnabled());
    }

    public function testSkipVerifiedUsersCanBeDisabled(): void
    {
        $GLOBALS['_test_options'][SettingsRepository::OPTION_KEY] = ['woocommerce' => ['skip_verified_users' => false]];

        $config = new WooCommerceConfig(new VerificationConfig(), new SettingsRepository());

        $this->assertFalse($config->shouldSkipVerifiedUsers());
    }

    // --- shouldSkipForBillingValue ---

    public function testShouldSkipForBillingValueReturnsTrueWhenEmailMatches(): void
    {
        $GLOBALS['_test_options'][SettingsRepository::OPTION_KEY] = ['woocommerce' => ['skip_verified_users' => true]];
        $GLOBALS['_test_current_user_id'] = 1;
        $GLOBALS['_test_user_meta'][1]['wsms_email_verified'] = '1';

        $user = new \WP_User(1);
        $user->user_email = 'test@example.com';
        $GLOBALS['_test_userdata'] = $user;

        $config = new WooCommerceConfig(new VerificationConfig(), new SettingsRepository());

        $this->assertTrue($config->shouldSkipForBillingValue('email', 'test@example.com'));
    }

    public function testShouldSkipForBillingValueReturnsFalseWhenEmailDiffers(): void
    {
        $GLOBALS['_test_options'][SettingsRepository::OPTION_KEY] = ['woocommerce' => ['skip_verified_users' => true]];
        $GLOBALS['_test_current_user_id'] = 1;
        $GLOBALS['_test_user_meta'][1]['wsms_email_verified'] = '1';

        $user = new \WP_User(1);
        $user->user_email = 'account@example.com';
        $GLOBALS['_test_userdata'] = $user;

        $config = new WooCommerceConfig(new VerificationConfig(), new SettingsRepository());

        $this->assertFalse($config->shouldSkipForBillingValue('email', 'billing@other.com'));
    }

    public function testShouldSkipForBillingValueReturnsFalseForGuest(): void
    {
        $GLOBALS['_test_options'][SettingsRepository::OPTION_KEY] = ['woocommerce' => ['skip_verified_users' => true]];
        $GLOBALS['_test_current_user_id'] = 0;

        $config = new WooCommerceConfig(new VerificationConfig(), new SettingsRepository());

        $this->assertFalse($config->shouldSkipForBillingValue('email', 'guest@example.com'));
    }

    public function testShouldSkipForBillingValueReturnsFalseWhenNotVerified(): void
    {
        $GLOBALS['_test_options'][SettingsRepository::OPTION_KEY] = ['woocommerce' => ['skip_verified_users' => true]];
        $GLOBALS['_test_current_user_id'] = 1;
        // No wsms_email_verified meta.

        $config = new WooCommerceConfig(new VerificationConfig(), new SettingsRepository());

        $this->assertFalse($config->shouldSkipForBillingValue('email', 'test@example.com'));
    }

    public function testShouldSkipForBillingValueReturnsFalseWhenSkipDisabled(): void
    {
        $GLOBALS['_test_options'][SettingsRepository::OPTION_KEY] = ['woocommerce' => ['skip_verified_users' => false]];
        $GLOBALS['_test_current_user_id'] = 1;
        $GLOBALS['_test_user_meta'][1]['wsms_email_verified'] = '1';

        $config = new WooCommerceConfig(new VerificationConfig(), new SettingsRepository());

        $this->assertFalse($config->shouldSkipForBillingValue('email', 'test@example.com'));
    }

    public function testShouldSkipForBillingValueCaseInsensitive(): void
    {
        $GLOBALS['_test_options'][SettingsRepository::OPTION_KEY] = ['woocommerce' => ['skip_verified_users' => true]];
        $GLOBALS['_test_current_user_id'] = 1;
        $GLOBALS['_test_user_meta'][1]['wsms_email_verified'] = '1';

        $user = new \WP_User(1);
        $user->user_email = 'Test@Example.com';
        $GLOBALS['_test_userdata'] = $user;

        $config = new WooCommerceConfig(new VerificationConfig(), new SettingsRepository());

        $this->assertTrue($config->shouldSkipForBillingValue('email', 'test@example.com'));
    }

    // --- getVerifiedAccountValue ---

    public function testGetVerifiedAccountValueReturnsEmailWhenVerified(): void
    {
        $GLOBALS['_test_options'][SettingsRepository::OPTION_KEY] = ['woocommerce' => ['skip_verified_users' => true]];
        $GLOBALS['_test_current_user_id'] = 1;
        $GLOBALS['_test_user_meta'][1]['wsms_email_verified'] = '1';

        $user = new \WP_User(1);
        $user->user_email = 'verified@example.com';
        $GLOBALS['_test_userdata'] = $user;

        $config = new WooCommerceConfig(new VerificationConfig(), new SettingsRepository());

        $this->assertSame('verified@example.com', $config->getVerifiedAccountValue('email'));
    }

    public function testGetVerifiedAccountValueReturnsNullForGuest(): void
    {
        $GLOBALS['_test_current_user_id'] = 0;

        $config = new WooCommerceConfig(new VerificationConfig(), new SettingsRepository());

        $this->assertNull($config->getVerifiedAccountValue('email'));
    }

    protected function tearDown(): void
    {
        $GLOBALS['_test_options'] = [];
        $GLOBALS['_test_user_meta'] = [];
        $GLOBALS['_test_current_user_id'] = 0;
        unset($GLOBALS['_test_userdata']);
    }
}
