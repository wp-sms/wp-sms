<?php

namespace WSms\Tests\Unit\Verification\Plugin\WooCommerce;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Branding\BrandingRepository;
use WSms\Verification\Plugin\WooCommerce\WooClassicCheckoutVerification;
use WSms\Verification\Plugin\WooCommerce\WooCommerceConfig;
use WSms\Verification\VerificationService;

class WooClassicCheckoutVerificationTest extends TestCase
{
    private WooClassicCheckoutVerification $checkout;
    private MockObject&VerificationService $service;
    private MockObject&WooCommerceConfig $config;

    protected function setUp(): void
    {
        $GLOBALS['_test_options'] = [];
        $GLOBALS['_test_user_meta'] = [];
        $GLOBALS['_test_current_user_id'] = 0;

        $this->service = $this->createMock(VerificationService::class);
        $this->config = $this->createMock(WooCommerceConfig::class);
        $this->config->method('hasAnyCheckoutEnabled')->willReturn(true);

        $this->checkout = new WooClassicCheckoutVerification($this->service, $this->config, new BrandingRepository());
    }

    public function testValidateCheckoutPassesWhenEmailVerified(): void
    {
        $this->config->method('isCheckoutEmailEnabled')->willReturn(true);
        $this->config->method('isCheckoutPhoneEnabled')->willReturn(false);
        $this->config->method('shouldSkipForBillingValue')->willReturn(false);

        $this->service->method('isVerified')
            ->with('email', 'test@example.com', 'valid-token')
            ->willReturn(true);

        $_POST['billing_email'] = 'test@example.com';
        $_POST['wsms_checkout_token_email'] = 'valid-token';

        $errors = new \WP_Error();
        $this->checkout->validateCheckout([], $errors);

        $this->assertEmpty($errors->get_error_code());
    }

    public function testValidateCheckoutFailsWhenEmailNotVerified(): void
    {
        $this->config->method('isCheckoutEmailEnabled')->willReturn(true);
        $this->config->method('isCheckoutPhoneEnabled')->willReturn(false);
        $this->config->method('shouldSkipForBillingValue')->willReturn(false);

        $this->service->method('isVerified')->willReturn(false);

        $_POST['billing_email'] = 'test@example.com';
        $_POST['wsms_checkout_token_email'] = 'invalid-token';

        $errors = new \WP_Error();
        $this->checkout->validateCheckout([], $errors);

        $this->assertSame('wsms_email_not_verified', $errors->get_error_code());
    }

    public function testValidateCheckoutSkipsWhenBillingEmailMatchesAccount(): void
    {
        $this->config->method('isCheckoutEmailEnabled')->willReturn(true);
        $this->config->method('isCheckoutPhoneEnabled')->willReturn(false);
        $this->config->method('shouldSkipForBillingValue')
            ->with('email', 'account@example.com')
            ->willReturn(true);

        // No token needed — skip.
        $_POST['billing_email'] = 'account@example.com';

        $errors = new \WP_Error();
        $this->checkout->validateCheckout([], $errors);

        $this->assertEmpty($errors->get_error_code());
    }

    public function testValidateCheckoutRequiresVerificationWhenBillingEmailDiffers(): void
    {
        $this->config->method('isCheckoutEmailEnabled')->willReturn(true);
        $this->config->method('isCheckoutPhoneEnabled')->willReturn(false);
        $this->config->method('shouldSkipForBillingValue')
            ->with('email', 'different@example.com')
            ->willReturn(false);

        $this->service->method('isVerified')->willReturn(false);

        $_POST['billing_email'] = 'different@example.com';
        $_POST['wsms_checkout_token_email'] = '';

        $errors = new \WP_Error();
        $this->checkout->validateCheckout([], $errors);

        $this->assertSame('wsms_email_not_verified', $errors->get_error_code());
    }

    public function testValidateCheckoutPassesWhenPhoneVerified(): void
    {
        $this->config->method('isCheckoutEmailEnabled')->willReturn(false);
        $this->config->method('isCheckoutPhoneEnabled')->willReturn(true);
        $this->config->method('shouldSkipForBillingValue')->willReturn(false);

        $this->service->method('isVerified')
            ->with('phone', '+1234567890', 'phone-token')
            ->willReturn(true);

        $_POST['billing_phone'] = '+1234567890';
        $_POST['wsms_checkout_token_phone'] = 'phone-token';

        $errors = new \WP_Error();
        $this->checkout->validateCheckout([], $errors);

        $this->assertEmpty($errors->get_error_code());
    }

    public function testSaveOrderMetaAfterVerification(): void
    {
        $this->config->method('isCheckoutEmailEnabled')->willReturn(true);
        $this->config->method('isCheckoutPhoneEnabled')->willReturn(false);
        $this->config->method('shouldSkipForBillingValue')->willReturn(false);
        $this->service->method('isVerified')->willReturn(true);

        $_POST['billing_email'] = 'test@example.com';
        $_POST['wsms_checkout_token_email'] = 'email-token';

        $errors = new \WP_Error();
        $this->checkout->validateCheckout([], $errors);

        $order = new \WC_Order_Stub(42);
        $GLOBALS['_test_wc_order'] = $order;
        $this->checkout->saveOrderMeta(42, []);

        $this->assertSame('1', $order->get_meta('_wsms_email_verified'));
    }

    public function testRenderWidgetContainersAlwaysRendersWhenEnabled(): void
    {
        $this->config->method('isCheckoutEmailEnabled')->willReturn(true);
        $this->config->method('isCheckoutPhoneEnabled')->willReturn(false);

        ob_start();
        $this->checkout->renderWidgetContainers(null);
        $output = ob_get_clean();

        $this->assertStringContainsString('wsms-woo-verify-email', $output);
    }

    protected function tearDown(): void
    {
        unset(
            $_POST['billing_email'],
            $_POST['billing_phone'],
            $_POST['wsms_checkout_token_email'],
            $_POST['wsms_checkout_token_phone'],
        );
        $GLOBALS['_test_options'] = [];
        $GLOBALS['_test_user_meta'] = [];
        $GLOBALS['_test_current_user_id'] = 0;
        unset($GLOBALS['_test_wc_order']);
    }
}
