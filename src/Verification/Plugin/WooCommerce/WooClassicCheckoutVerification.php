<?php

namespace WSms\Verification\Plugin\WooCommerce;

use WSms\Branding\BrandingRepository;
use WSms\Support\PhoneValidator;
use WSms\Verification\FormVerification;
use WSms\Verification\VerificationService;

defined('ABSPATH') || exit;

class WooClassicCheckoutVerification extends FormVerification
{
    private bool $emailVerified = false;
    private bool $phoneVerified = false;

    public function __construct(
        VerificationService $verificationService,
        private WooCommerceConfig $config,
        BrandingRepository $brandingRepo,
    ) {
        parent::__construct($verificationService, $brandingRepo);
    }

    public function registerHooks(): void
    {
        if (!$this->config->hasAnyCheckoutEnabled()) {
            return;
        }

        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('woocommerce_after_checkout_billing_form', [$this, 'renderWidgetContainers']);
        add_action('woocommerce_after_checkout_validation', [$this, 'validateCheckout'], 10, 2);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'saveOrderMeta'], 10, 2);
    }

    public function enqueueAssets(): void
    {
        if (!function_exists('is_checkout') || !is_checkout()) {
            return;
        }

        parent::enqueueAssets();
    }

    public function renderWidgetContainers($checkout): void
    {
        if ($this->config->isCheckoutEmailEnabled()) {
            echo self::renderVerifyHtml('email', '#billing_email', 'wsms_checkout_token_email', [
                'skip' => $this->config->getVerifiedAccountValue('email'),
            ]);
        }

        if ($this->config->isCheckoutPhoneEnabled()) {
            echo self::renderVerifyHtml('phone', '#billing_phone', 'wsms_checkout_token_phone', [
                'skip' => $this->config->getVerifiedAccountValue('phone'),
            ]);
        }
    }

    public function validateCheckout($data, $errors): void
    {
        if ($this->config->isCheckoutEmailEnabled()) {
            $email = sanitize_text_field(wp_unslash($_POST['billing_email'] ?? ''));

            if ($this->config->shouldSkipForBillingValue('email', $email)) {
                $this->emailVerified = true;
            } else {
                $token = sanitize_text_field(wp_unslash($_POST['wsms_checkout_token_email'] ?? ''));

                if (empty($token) || !$this->verificationService->isVerified('email', $email, $token)) {
                    $errors->add('wsms_email_not_verified', __('Please verify your email address before placing your order.', 'wp-sms'));
                } else {
                    $this->emailVerified = true;
                }
            }
        }

        if ($this->config->isCheckoutPhoneEnabled()) {
            $phone = sanitize_text_field(wp_unslash($_POST['billing_phone'] ?? ''));

            if ($this->config->shouldSkipForBillingValue('phone', $phone)) {
                $this->phoneVerified = true;
            } elseif (!PhoneValidator::isE164($phone)) {
                // Skip verification for non-E.164 phones — don't block checkout.
                $this->phoneVerified = true;
            } else {
                $token = sanitize_text_field(wp_unslash($_POST['wsms_checkout_token_phone'] ?? ''));

                if (empty($token) || !$this->verificationService->isVerified('phone', $phone, $token)) {
                    $errors->add('wsms_phone_not_verified', __('Please verify your phone number before placing your order.', 'wp-sms'));
                } else {
                    $this->phoneVerified = true;
                }
            }
        }
    }

    public function saveOrderMeta($orderId, $data): void
    {
        if (!$this->emailVerified && !$this->phoneVerified) {
            return;
        }

        $order = wc_get_order($orderId);
        if (!$order) {
            return;
        }

        if ($this->emailVerified) {
            $order->update_meta_data('_wsms_email_verified', '1');
        }

        if ($this->phoneVerified) {
            $order->update_meta_data('_wsms_phone_verified', '1');
        }

        $order->save();
    }
}
