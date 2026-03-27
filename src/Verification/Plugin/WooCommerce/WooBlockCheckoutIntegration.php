<?php

namespace WSms\Verification\Plugin\WooCommerce;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;
use WSms\Branding\BrandingRepository;
use WSms\Verification\EnqueuesVerifyWidget;

defined('ABSPATH') || exit;

class WooBlockCheckoutIntegration implements IntegrationInterface
{
    use EnqueuesVerifyWidget;

    public function __construct(
        private WooCommerceConfig $config,
        private BrandingRepository $brandingRepo,
    ) {
    }

    public function get_name(): string
    {
        return 'wsms-checkout-verify';
    }

    public function initialize(): void
    {
        $version = WP_SMS_VERSION;

        $this->enqueueVerifyWidget($this->brandingRepo->get('primary_color'));

        $frontendAsset = include WP_SMS_DIR . 'public/blocks/wc-checkout-verify/frontend.asset.php';
        $frontendDeps = $frontendAsset['dependencies'] ?? [];
        $frontendDeps[] = 'wsms-verify-widget';

        wp_register_script(
            'wsms-wc-checkout-verify-frontend',
            WP_SMS_URL . 'public/blocks/wc-checkout-verify/frontend.js',
            $frontendDeps,
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

        wp_register_style('wsms-wc-checkout-verify-style', false, [], $version);
        wp_enqueue_style('wsms-wc-checkout-verify-style');
        wp_add_inline_style('wsms-wc-checkout-verify-style', <<<'CSS'
        #wsms-checkout-verify-slot {
            margin-top: 16px;
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
