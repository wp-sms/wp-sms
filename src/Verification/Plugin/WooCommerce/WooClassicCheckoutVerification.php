<?php

namespace WSms\Verification\Plugin\WooCommerce;

use WSms\Branding\BrandingRepository;
use WSms\Verification\EnqueuesVerifyWidget;
use WSms\Verification\VerificationService;

defined('ABSPATH') || exit;

class WooClassicCheckoutVerification
{
    use EnqueuesVerifyWidget;

    private bool $emailVerified = false;
    private bool $phoneVerified = false;

    public function __construct(
        private VerificationService $verificationService,
        private WooCommerceConfig $config,
        private BrandingRepository $brandingRepo,
    ) {
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

        $this->enqueueVerifyWidget($this->brandingRepo->get('primary_color'));

        // Pass verified account values so JS can skip when billing matches.
        $cfg = [
            'email' => $this->config->isCheckoutEmailEnabled(),
            'phone' => $this->config->isCheckoutPhoneEnabled(),
            'skipEmail' => $this->config->getVerifiedAccountValue('email'),
            'skipPhone' => $this->config->getVerifiedAccountValue('phone'),
        ];

        wp_add_inline_script('wsms-verify-widget', sprintf(
            <<<'JS'
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof wsmsVerify === 'undefined') return;

                var cfg = %s;

                function initWsmsWooVerify() {
                    ['email', 'phone'].forEach(function(channel) {
                        if (!cfg[channel]) return;
                        var fieldId = channel === 'email' ? 'billing_email' : 'billing_phone';
                        var field = document.getElementById(fieldId);
                        var container = document.getElementById('wsms-woo-verify-' + channel);
                        var flag = document.getElementById('wsms-woo-token-' + channel);
                        if (!field || !container || !flag) return;

                        var skipValue = channel === 'email' ? cfg.skipEmail : cfg.skipPhone;

                        // Destroy any orphaned widget from DOM replacement.
                        if (container.children.length) {
                            wsmsVerify.destroy(container);
                        }

                        if (field.dataset.wsmsInitialized) return;
                        field.dataset.wsmsInitialized = '1';

                        var lastValue = '';
                        function shouldSkip(value) {
                            return skipValue && value.toLowerCase() === skipValue.toLowerCase();
                        }

                        field.addEventListener('blur', function() {
                            var value = field.value.trim();
                            if (!value || value === lastValue) return;
                            lastValue = value;
                            flag.value = '';

                            if (shouldSkip(value)) {
                                wsmsVerify.destroy(container);
                                container.style.display = 'none';
                                return;
                            }

                            container.style.display = 'block';
                            wsmsVerify.mount(container, {
                                channel: channel,
                                identifier: value,
                                onVerified: function(sessionToken) {
                                    flag.value = sessionToken;
                                },
                            });
                        });

                        field.addEventListener('change', function() {
                            if (flag.value && field.value.trim() !== lastValue) {
                                flag.value = '';
                                lastValue = '';
                                wsmsVerify.destroy(container);
                            }
                        });
                    });
                }

                initWsmsWooVerify();
                if (typeof jQuery !== 'undefined') {
                    jQuery(document.body).on('updated_checkout', initWsmsWooVerify);
                }
            });
            JS,
            wp_json_encode($cfg),
        ));
    }

    public function renderWidgetContainers($checkout): void
    {
        // Always render containers when channel is enabled — JS handles skip logic
        // based on billing value vs verified account value.
        if ($this->config->isCheckoutEmailEnabled()) {
            echo '<div id="wsms-woo-verify-email" class="wsms-woo-verify-container" style="display:block"></div>';
            echo '<input type="hidden" id="wsms-woo-token-email" name="wsms_checkout_token_email" value="">';
        }

        if ($this->config->isCheckoutPhoneEnabled()) {
            echo '<div id="wsms-woo-verify-phone" class="wsms-woo-verify-container" style="display:block"></div>';
            echo '<input type="hidden" id="wsms-woo-token-phone" name="wsms_checkout_token_phone" value="">';
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
