<?php

namespace WP_SMS;

if (!defined('ABSPATH')) exit;

class Admin
{
    public $sms;
    private $db;
    private $tb_prefix;
    private $options;

    public function __construct()
    {
        global $wpdb;

        $this->db        = $wpdb;
        $this->tb_prefix = $wpdb->prefix;
        $this->options   = Option::getOptions();

        $this->init();

        // Add Actions
        add_action('admin_bar_menu', array($this, 'admin_bar'), 40);
        add_action('dashboard_glance_items', array($this, 'dashboard_glance'));

        // Add Filters
        add_filter('plugin_row_meta', array($this, 'meta_links'), 0, 2);
        add_filter('admin_body_class', array($this, 'modify_admin_body_classes'));
        add_filter('admin_footer_text', array($this, 'wpsms_custom_footer'), 999);
        add_filter('update_footer', array($this, 'wpsms_update_footer'), 999);
    }

    /**
     * Include footer
     */
    public function wpsms_custom_footer($text)
    {
        $screen = get_current_screen();
        if (stristr($screen->id, 'wsms') or stristr($screen->id, 'wp-sms') or $screen->base == 'post' or $screen->id == 'edit-wpsms-command' or $screen->id == 'edit-sms-campaign') {
            $text = sprintf(
            /* translators: 1: URL to review page 2: aria-label text 3: title text 4: link text */
                __('Please rate <strong>WP SMS</strong> <a href="%1$s" aria-label="%2$s" title="%3$s" target="_blank">%4$s</a> to help us spread the word. Thank you!', 'wp-sms'),
                'https://wordpress.org/support/plugin/wp-sms/reviews/',
                esc_attr__('Rate WP SMS with five stars on WordPress.org', 'wp-sms'),
                esc_attr__('Rate WP SMS', 'wp-sms'),
                esc_html__('on WordPress.org', 'wp-sms')
            );
        }
        return $text;
    }

    public function wpsms_update_footer($content)
    {
        $screen = get_current_screen();
        if (stristr($screen->id, 'wsms') or stristr($screen->id, 'wp-sms') or $screen->base == 'post' or $screen->id == 'edit-wpsms-command' or $screen->id == 'edit-sms-campaign') {
            global $wp_version;
            $plugin_data    = get_plugin_data(WP_SMS_DIR . 'wp-sms.php');
            $plugin_version = $plugin_data['Version'];
            $content        = sprintf('<p id="footer-upgrade" class="alignright">%s | %s %s</p>',
                esc_html__('WordPress', 'wp-sms') . ' ' . esc_html($wp_version),
                esc_html(__('WP SMS', 'wp-sms')),
                esc_html($plugin_version)
            );
        }
        return $content;
    }

    /**
     * Admin bar plugin
     */
    public function admin_bar()
    {
        global $wp_admin_bar;

        if (is_super_admin() && is_admin_bar_showing() && current_user_can('wpsms_sendsms')) {
            $credit = get_option('wpsms_gateway_credit');
            if (!empty($this->options['account_credit_in_menu']) and !is_object($credit)) {
                $wp_admin_bar->add_menu(array(
                    'id'    => 'wp-credit-sms',
                    'title' => '<span class="ab-icon"></span>' . $credit,
                    'href'  => WP_SMS_ADMIN_URL . 'admin.php?page=wsms'
                ));
            }
        }

        if (current_user_can('wpsms_sendsms')) {
            $wp_admin_bar->add_menu(array(
                'id'     => 'wp-send-sms',
                'parent' => 'new-content',
                'title'  => esc_html__('SMS', 'wp-sms'),
                'href'   => WP_SMS_ADMIN_URL . 'admin.php?page=wsms'
            ));
        }
    }

    /**
     * Dashboard glance plugin
     */
    public function dashboard_glance()
    {
        $subscribe = $this->db->get_var("SELECT COUNT(*) FROM {$this->tb_prefix}sms_subscribes");
        $credit    = get_option('wpsms_gateway_credit');

        // translators: %s: Number of subscribers
        echo "<li class='wpsms-subscribe-count'><a href='" . WP_SMS_ADMIN_URL . "admin.php?page=wsms'>" . sprintf(esc_html__('%s Subscriber', 'wp-sms'), esc_html($subscribe)) . "</a></li>"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        if (!is_object($credit)) {
            // translators: %s: SMS credit
            echo "<li class='wpsms-credit-count'><a href='" . WP_SMS_ADMIN_URL . "admin.php?page=wsms&tab=gateway'>" . sprintf(esc_html__('%s SMS Credit', 'wp-sms'), esc_html($credit)) . "</a></li>"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }

    /**
     * Administrator add Meta Links
     *
     * @param $links
     * @param $file
     *
     * @return array
     */
    public function meta_links($links, $file)
    {
        if ($file == 'wp-sms/wp-sms.php') {
            $rate_url = 'https://wordpress.org/support/view/plugin-reviews/wp-sms/';
            $links[]  = '<a href="' . $rate_url . '" target="_blank" class="wpsms-plugin-meta-link" title="' . esc_html__('Click here to rate and review this plugin on WordPress.org', 'wp-sms') . '">' . esc_html__('Rate this plugin', 'wp-sms') . '</a>';
        }

        return $links;
    }

    /**
     * Adding new capability in the plugin
     */
    public function add_cap()
    {
        // Get administrator role
        $role = get_role('administrator');

        $role->add_cap('wpsms_sendsms');
        $role->add_cap('wpsms_outbox');
        $role->add_cap('wpsms_inbox');
        $role->add_cap('wpsms_subscribers');
        $role->add_cap('wpsms_setting');
    }

    /**
     * Initial plugin
     */
    private function init()
    {
        // Check exists require function
        if (!function_exists('wp_get_current_user')) {
            include(ABSPATH . "wp-includes/pluggable.php");
        }

        // Add plugin caps to admin role
        if (is_admin() and is_super_admin()) {
            $this->add_cap();
        }
    }

    /**
     * Modifies the admin body class.
     *
     * @date    21/02/2022
     * @param string $classes Space-separated list of CSS classes.
     * @return  string
     *
     */
    public function modify_admin_body_classes($classes)
    {
        // Add class for the admin body only for plugin's pages
        if (isset($_GET['page']) && $_GET['page'] === 'wsms') {
            $classes .= ' sms_page_wsms';
        }

        return $classes;
    }
}

new Admin();
