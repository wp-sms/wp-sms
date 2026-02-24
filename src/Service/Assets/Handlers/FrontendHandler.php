<?php

namespace WP_SMS\Service\Assets\Handlers;

use WP_SMS\Abstracts\BaseAssets;
use WP_SMS\Controller\PublicSubscribeAjax;
use WP_SMS\Controller\PublicUnsubscribeAjax;
use WP_SMS\Controller\PublicVerifySubscribeAjax;

if (!defined('ABSPATH')) exit;

class FrontendHandler extends BaseAssets
{
    public function __construct()
    {
        $this->pluginUrl    = WP_SMS_URL;
        $this->pluginDir    = WP_SMS_DIR;
        $this->assetVersion = WP_SMS_VERSION;

        $this->setContext('frontend');
        $this->setPrefix('wp-sms-frontend');
        $this->setAssetDir('public');

        add_action('wp_enqueue_scripts', [$this, 'styles']);
        add_action('wp_enqueue_scripts', [$this, 'scripts']);
    }

    /**
     * Enqueue frontend styles.
     *
     * @return void
     */
    public function styles()
    {
        // Admin bar style on frontend
        if (is_admin_bar_showing()) {
            wp_register_style('wpsms-admin-bar', $this->getUrl('css/admin-bar.css'), [], $this->getVersion());
            wp_enqueue_style('wpsms-admin-bar');
        }

        // Check if "Disable Style" in frontend is active or not
        if (!wp_sms_get_option('disable_style_in_front')) {
            wp_register_style('wpsms-front', $this->getUrl('css/front-styles.css'), [], $this->getVersion());
            wp_enqueue_style('wpsms-front');
        }
    }

    /**
     * Enqueue frontend scripts.
     *
     * @param string $hook
     * @return void
     */
    public function scripts(string $hook = '')
    {
        global $sms;

        wp_register_script('wp-sms-front-script', $this->getUrl('js/frontend.min.js'), ['jquery'], $this->getVersion(), true);
        wp_enqueue_script('wp-sms-front-script');

        wp_localize_script('wp-sms-front-script', 'wpsms_ajax_object', [
            'subscribe_ajax_url'        => PublicSubscribeAjax::url(),
            'unsubscribe_ajax_url'      => PublicUnsubscribeAjax::url(),
            'verify_subscribe_ajax_url' => PublicVerifySubscribeAjax::url(),
            'unknown_error'             => esc_html__('Unknown Error! Check your connection and try again.', 'wp-sms'),
            'loading_text'              => esc_html__('Loading...', 'wp-sms'),
            'subscribe_text'            => esc_html__('Subscribe', 'wp-sms'),
            'activation_text'           => esc_html__('Activate', 'wp-sms'),
            'gdpr_error_text'           => esc_html__('Please accept the privacy checkbox to continue.', 'wp-sms'),
            'sender'                    => $sms->from,
            'front_sms_endpoint_url'    => apply_filters('wp_sms_send_front_sms_ajax', null)
        ]);
    }
}
