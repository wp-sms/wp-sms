<?php

namespace WSms\Verification\Plugin\WooCommerce;

use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;
use WSms\Support\PhoneValidator;
use WSms\Verification\VerificationService;

defined('ABSPATH') || exit;

class WooBlockCheckoutValidation
{
    public function __construct(
        private VerificationService $verificationService,
        private WooCommerceConfig $config,
    ) {
    }

    public function registerHooks(): void
    {
        if (!$this->config->hasAnyCheckoutEnabled()) {
            return;
        }

        if (did_action('woocommerce_blocks_loaded')) {
            $this->registerEndpointData();
        } else {
            add_action('woocommerce_blocks_loaded', [$this, 'registerEndpointData']);
        }
    }

    public function registerEndpointData(): void
    {
        if (!function_exists('woocommerce_store_api_register_endpoint_data')) {
            return;
        }

        woocommerce_store_api_register_endpoint_data([
            'endpoint'        => 'checkout',
            'namespace'       => 'wsms-checkout-verify',
            'data_callback'   => fn () => [
                'email_session_token' => '',
                'phone_session_token' => '',
            ],
            'schema_callback' => fn () => [
                'email_session_token' => [
                    'description' => 'WSMS email verification session token',
                    'type'        => 'string',
                    'context'     => ['view', 'edit'],
                ],
                'phone_session_token' => [
                    'description' => 'WSMS phone verification session token',
                    'type'        => 'string',
                    'context'     => ['view', 'edit'],
                ],
            ],
        ]);

        add_action(
            'woocommerce_store_api_checkout_update_order_from_request',
            [$this, 'validateBlockCheckout'],
            10,
            2,
        );
    }

    public function validateBlockCheckout($order, $request): void
    {
        $extensions = $request->get_param('extensions') ?? [];
        $data = $extensions['wsms-checkout-verify'] ?? [];
        $metaUpdated = false;

        if ($this->config->isCheckoutEmailEnabled()) {
            $email = $order->get_billing_email();

            if ($this->config->shouldSkipForBillingValue('email', $email)) {
                $order->update_meta_data('_wsms_email_verified', '1');
                $metaUpdated = true;
            } else {
                $token = sanitize_text_field($data['email_session_token'] ?? '');

                if (empty($token) || !$this->verificationService->isVerified('email', $email, $token)) {
                    throw new RouteException(
                        'wsms_email_not_verified',
                        __('Please verify your email address before placing your order.', 'wp-sms'),
                        400,
                    );
                }

                $order->update_meta_data('_wsms_email_verified', '1');
                $metaUpdated = true;
            }
        }

        if ($this->config->isCheckoutPhoneEnabled()) {
            $phone = $order->get_billing_phone();

            if ($this->config->shouldSkipForBillingValue('phone', $phone)) {
                $order->update_meta_data('_wsms_phone_verified', '1');
                $metaUpdated = true;
            } elseif (!PhoneValidator::isE164($phone)) {
                // Skip verification for non-E.164 phones — don't block checkout.
            } else {
                $token = sanitize_text_field($data['phone_session_token'] ?? '');

                if (empty($token) || !$this->verificationService->isVerified('phone', $phone, $token)) {
                    throw new RouteException(
                        'wsms_phone_not_verified',
                        __('Please verify your phone number before placing your order.', 'wp-sms'),
                        400,
                    );
                }

                $order->update_meta_data('_wsms_phone_verified', '1');
                $metaUpdated = true;
            }
        }

        if ($metaUpdated) {
            $order->save();
        }
    }
}
