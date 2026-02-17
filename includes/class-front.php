<?php

namespace WP_SMS;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

class Front
{
    public function __construct()
    {
        add_action('admin_bar_menu', array($this, 'admin_bar'));
    }

    /**
     * Admin bar plugin
     */
    public function admin_bar()
    {
        global $wp_admin_bar;
        if (is_super_admin() && is_admin_bar_showing()) {
            $credit = get_option('wpsms_gateway_credit');
            if (wp_sms_get_option('account_credit_in_menu') and !is_object($credit)) {
                $wp_admin_bar->add_menu(array(
                    'id'    => 'wp-credit-sms',
                    'title' => '<span class="ab-icon"></span>' . $credit,
                    'href'  => WP_SMS_ADMIN_URL . 'admin.php?page=wsms'
                ));
            }
        }

        $wp_admin_bar->add_menu(array(
            'id'     => 'wp-send-sms',
            'parent' => 'new-content',
            'title'  => esc_html__('SMS', 'wp-sms'),
            'href'   => WP_SMS_ADMIN_URL . '/admin.php?page=wsms'
        ));
    }
}

new Front();
