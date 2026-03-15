<?php

namespace WSms\Tests\Unit\Integrations\WooCommerce;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Integrations\WooCommerce\WooBlockCheckoutValidation;
use WSms\Integrations\WooCommerce\WooCommerceConfig;
use WSms\Verification\VerificationService;

class WooBlockCheckoutValidationTest extends TestCase
{
    private WooBlockCheckoutValidation $validation;
    private MockObject&VerificationService $service;
    private MockObject&WooCommerceConfig $config;

    protected function setUp(): void
    {
        $this->service = $this->createMock(VerificationService::class);
        $this->config = $this->createMock(WooCommerceConfig::class);
        $this->config->method('hasAnyCheckoutEnabled')->willReturn(true);

        $this->validation = new WooBlockCheckoutValidation($this->service, $this->config);
    }

    public function testValidatePassesWhenEmailVerified(): void
    {
        $this->config->method('isCheckoutEmailEnabled')->willReturn(true);
        $this->config->method('isCheckoutPhoneEnabled')->willReturn(false);
        $this->config->method('shouldSkipForBillingValue')->willReturn(false);

        $this->service->method('isVerified')
            ->with('email', 'test@example.com', 'valid-token')
            ->willReturn(true);

        $order = new \WC_Order_Stub(1);
        $order->set_billing_email('test@example.com');

        $request = new \WP_REST_Request();
        $request->set_param('extensions', [
            'wsms-checkout-verify' => ['email_session_token' => 'valid-token'],
        ]);

        $this->validation->validateBlockCheckout($order, $request);

        $this->assertSame('1', $order->get_meta('_wsms_email_verified'));
    }

    public function testValidateThrowsWhenEmailNotVerified(): void
    {
        $this->config->method('isCheckoutEmailEnabled')->willReturn(true);
        $this->config->method('isCheckoutPhoneEnabled')->willReturn(false);
        $this->config->method('shouldSkipForBillingValue')->willReturn(false);

        $this->service->method('isVerified')->willReturn(false);

        $order = new \WC_Order_Stub(1);
        $order->set_billing_email('test@example.com');

        $request = new \WP_REST_Request();
        $request->set_param('extensions', [
            'wsms-checkout-verify' => ['email_session_token' => 'bad-token'],
        ]);

        $this->expectException(\Exception::class);
        $this->validation->validateBlockCheckout($order, $request);
    }

    public function testValidateSkipsWhenBillingEmailMatchesAccount(): void
    {
        $this->config->method('isCheckoutEmailEnabled')->willReturn(true);
        $this->config->method('isCheckoutPhoneEnabled')->willReturn(false);
        $this->config->method('shouldSkipForBillingValue')
            ->with('email', 'account@example.com')
            ->willReturn(true);

        // No token needed.
        $order = new \WC_Order_Stub(1);
        $order->set_billing_email('account@example.com');

        $request = new \WP_REST_Request();
        $request->set_param('extensions', []);

        $this->validation->validateBlockCheckout($order, $request);

        $this->assertSame('1', $order->get_meta('_wsms_email_verified'));
    }

    public function testValidateThrowsWhenBillingEmailDiffersAndNoToken(): void
    {
        $this->config->method('isCheckoutEmailEnabled')->willReturn(true);
        $this->config->method('isCheckoutPhoneEnabled')->willReturn(false);
        $this->config->method('shouldSkipForBillingValue')
            ->with('email', 'different@example.com')
            ->willReturn(false);

        $this->service->method('isVerified')->willReturn(false);

        $order = new \WC_Order_Stub(1);
        $order->set_billing_email('different@example.com');

        $request = new \WP_REST_Request();
        $request->set_param('extensions', [
            'wsms-checkout-verify' => ['email_session_token' => ''],
        ]);

        $this->expectException(\Exception::class);
        $this->validation->validateBlockCheckout($order, $request);
    }

    public function testValidatePassesWhenPhoneVerified(): void
    {
        $this->config->method('isCheckoutEmailEnabled')->willReturn(false);
        $this->config->method('isCheckoutPhoneEnabled')->willReturn(true);
        $this->config->method('shouldSkipForBillingValue')->willReturn(false);

        $this->service->method('isVerified')
            ->with('phone', '+1234567890', 'phone-token')
            ->willReturn(true);

        $order = new \WC_Order_Stub(1);
        $order->set_billing_phone('+1234567890');

        $request = new \WP_REST_Request();
        $request->set_param('extensions', [
            'wsms-checkout-verify' => ['phone_session_token' => 'phone-token'],
        ]);

        $this->validation->validateBlockCheckout($order, $request);

        $this->assertSame('1', $order->get_meta('_wsms_phone_verified'));
    }

    public function testNoMetaStampedWhenBothDisabled(): void
    {
        $this->config->method('isCheckoutEmailEnabled')->willReturn(false);
        $this->config->method('isCheckoutPhoneEnabled')->willReturn(false);

        $order = new \WC_Order_Stub(1);
        $request = new \WP_REST_Request();
        $request->set_param('extensions', []);

        $this->validation->validateBlockCheckout($order, $request);

        $this->assertSame('', $order->get_meta('_wsms_email_verified'));
        $this->assertSame('', $order->get_meta('_wsms_phone_verified'));
    }
}
