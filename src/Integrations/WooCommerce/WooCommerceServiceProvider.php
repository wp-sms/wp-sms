<?php

namespace WSms\Integrations\WooCommerce;

use WSms\Container\ServiceContainer;
use WSms\Container\ServiceProvider;

defined('ABSPATH') || exit;

class WooCommerceServiceProvider implements ServiceProvider
{
    public function register(ServiceContainer $container): void
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        $container->register('integration.woo.config', fn () => new WooCommerceConfig(
            $container->get('verification.config'),
            $container->get('auth.settings'),
        ));

        $container->register('integration.woo.classic_checkout', fn () => new WooClassicCheckoutVerification(
            $container->get('verification.service'),
            $container->get('integration.woo.config'),
        ));

        $container->register('integration.woo.block_checkout', fn () => new WooBlockCheckoutIntegration(
            $container->get('integration.woo.config'),
        ));

        $container->register('integration.woo.block_validation', fn () => new WooBlockCheckoutValidation(
            $container->get('verification.service'),
            $container->get('integration.woo.config'),
        ));
    }

    public function boot(ServiceContainer $container): void
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        // Resolve config first (cheap) to gate heavier service construction.
        $config = $container->get('integration.woo.config');

        if ($config->hasAnyCheckoutEnabled()) {
            $container->get('integration.woo.classic_checkout')->registerHooks();
            $container->get('integration.woo.block_validation')->registerHooks();
            $container->get('integration.woo.block_checkout')->register();
        }

        // WC auth redirect — redirect My Account login/register to WSMS auth pages.
        if ($config->isAuthRedirectEnabled()) {
            add_action('template_redirect', function () use ($container, $config) {
                if (is_user_logged_in()) {
                    return;
                }

                if (!function_exists('is_account_page') || !is_account_page()) {
                    return;
                }

                if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url()) {
                    return;
                }

                $settings = $container->get('auth.settings');
                $baseUrl = $settings->get('auth_base_url', '/account');
                $redirectTo = urlencode(wc_get_page_permalink('myaccount'));

                wp_safe_redirect(home_url($baseUrl . '/login?redirect_to=' . $redirectTo));
                exit;
            });
        }

        // WC registration — enforce verify-at-signup via LoginGuard.
        add_action('woocommerce_created_customer', function (int $customerId, array $data, bool $passwordGenerated) use ($container) {
            $container->get('auth.login_guard')->enforceVerificationOnWcRegistration($customerId, $data, $passwordGenerated);
        }, 10, 3);
    }
}
