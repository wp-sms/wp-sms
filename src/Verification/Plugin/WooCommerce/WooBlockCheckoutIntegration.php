<?php

namespace WSms\Verification\Plugin\WooCommerce;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

defined('ABSPATH') || exit;

class WooBlockCheckoutIntegration implements IntegrationInterface
{
    public function __construct(private WooCommerceConfig $config)
    {
    }

    public function get_name(): string
    {
        return 'wsms-checkout-verify';
    }

    public function initialize(): void
    {
        $version = WP_SMS_VERSION;

        $frontendAsset = include WP_SMS_DIR . 'public/blocks/wc-checkout-verify/frontend.asset.php';

        wp_register_script(
            'wsms-wc-checkout-verify-frontend',
            WP_SMS_URL . 'public/blocks/wc-checkout-verify/frontend.js',
            $frontendAsset['dependencies'] ?? [],
            $frontendAsset['version'] ?? $version,
            true,
        );

        wp_register_script(
            'wsms-wc-checkout-verify',
            WP_SMS_URL . 'public/blocks/wc-checkout-verify/index.js',
            ['wp-blocks', 'wp-element', 'wp-components'],
            $version,
            true,
        );

        // Register and enqueue inline styles for the block checkout verification.
        wp_register_style('wsms-wc-checkout-verify-style', false, [], $version);
        wp_enqueue_style('wsms-wc-checkout-verify-style');
        wp_add_inline_style('wsms-wc-checkout-verify-style', <<<'CSS'
        #wsms-checkout-verify-slot {
            margin-top: 16px;
        }
        #wsms-checkout-verify-slot .wc-block-components-notice-banner__content a {
            text-decoration: underline;
            font-weight: 500;
        }
        #wsms-checkout-verify-slot .wc-block-components-button.wp-element-button {
            width: auto;
            display: inline-block;
            vertical-align: middle;
        }
        #wsms-checkout-verify-slot .wc-block-components-address-form {
            margin-top: 0;
        }
        #wsms-checkout-verify-slot .wsms-checkout-verify-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 12px;
        }
        CSS);
    }

    public function get_script_handles(): array
    {
        return ['wsms-wc-checkout-verify-frontend'];
    }

    public function get_editor_script_handles(): array
    {
        return ['wsms-wc-checkout-verify'];
    }

    public function get_script_data(): array
    {
        return [
            'emailEnabled' => $this->config->isCheckoutEmailEnabled(),
            'phoneEnabled' => $this->config->isCheckoutPhoneEnabled(),
            'skipEmail'    => $this->config->getVerifiedAccountValue('email'),
            'skipPhone'    => $this->config->getVerifiedAccountValue('phone'),
            'restUrl'      => rest_url('wsms/v1/'),
            'nonce'        => wp_create_nonce('wp_rest'),
        ];
    }

    public function register(): void
    {
        add_action('woocommerce_blocks_checkout_block_registration', function ($registry) {
            $registry->register($this);
        });

        // Inject portal target inside the contact information step content.
        add_filter('render_block_woocommerce/checkout-contact-information-block', function (string $html): string {
            $slot = '<div id="wsms-checkout-verify-slot"></div>';
            // Insert before the last closing </div> of the step content.
            $pos = strrpos($html, '</div>');
            if ($pos !== false) {
                return substr_replace($html, $slot . '</div>', $pos);
            }
            return $html . $slot;
        });
    }
}
