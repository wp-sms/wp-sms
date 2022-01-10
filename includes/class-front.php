<?php

namespace WP_SMS;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

class Front
{

    public function __construct()
    {

        $this->options = Option::getOptions();

        // Load assets
        add_action('wp_enqueue_scripts', array($this, 'front_assets'));
        add_action('admin_bar_menu', array($this, 'admin_bar'));
    }

    /**
     * Include front table
     *
     * @param Not param
     */
    public function front_assets()
    {

        //Register admin-bar.css for whole admin area
        if (is_admin_bar_showing()) {
            wp_register_style('wpsms-admin-bar', WP_SMS_URL . 'assets/css/admin-bar.css', true, WP_SMS_VERSION);
            wp_enqueue_style('wpsms-admin-bar');
        }

        // Check if "Disable Style" in frontend is active or not
        if (empty($this->options['disable_style_in_front']) or (isset($this->options['disable_style_in_front']) and !$this->options['disable_style_in_front'])) {
            wp_register_style('wpsms-subscribe', WP_SMS_URL . 'assets/css/subscribe.css', true, WP_SMS_VERSION);
            wp_enqueue_style('wpsms-subscribe');
        }
    }

    /**
     * Admin bar plugin
     */
    public function admin_bar()
    {
        global $wp_admin_bar;
        if (is_super_admin() && is_admin_bar_showing()) {
            $credit = get_option('wpsms_gateway_credit');
            if (isset($this->options['account_credit_in_menu']) and !is_object($credit)) {
                $wp_admin_bar->add_menu(array(
                    'id'    => 'wp-credit-sms',
                    'title' => '<span class="ab-icon"></span>' . $credit,
                    'href'  => WP_SMS_ADMIN_URL . '/admin.php?page=wp-sms-settings'
                ));
            }
        }

        $wp_admin_bar->add_menu(array(
            'id'     => 'wp-send-sms',
            'parent' => 'new-content',
            'title'  => __('SMS', 'wp-sms'),
            'href'   => WP_SMS_ADMIN_URL . '/admin.php?page=wp-sms'
        ));
    }
}

new Front();
