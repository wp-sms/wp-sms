<?php

namespace WP_SMS;

class Admin
{
    public $sms;
    private $db;
    private $tb_prefix;
    private $settings;
    private $options;

    public function __construct()
    {
        global $wpdb;

        $this->db        = $wpdb;
        $this->tb_prefix = $wpdb->prefix;
        $this->settings  = new Settings();
        $this->options   = Option::getOptions();

        $this->init();

        // Add Actions
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        add_action('admin_bar_menu', array($this, 'admin_bar'));
        add_action('dashboard_glance_items', array($this, 'dashboard_glance'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_notices', array($this, 'displayFlashNotice'));
        add_action('init', array($this, 'do_output_buffer'));

        // Add Filters
        add_filter('plugin_row_meta', array($this, 'meta_links'), 0, 2);
        add_filter('set-screen-option', array($this, 'set_screen_option'), 10, 3);
        add_filter('admin_body_class', array($this, 'modify_admin_body_classes'));
    }

    /**
     * Include admin assets
     */
    public function admin_assets()
    {
        global $sms;

        // Register admin-bar.css for whole admin area
        if (is_admin_bar_showing()) {
            wp_register_style('wpsms-admin-bar', WP_SMS_URL . 'assets/css/admin-bar.css', true, WP_SMS_VERSION);
            wp_enqueue_style('wpsms-admin-bar');
        }

        $screen = get_current_screen();

        wp_register_script('wpsms-quick-reply', WP_SMS_URL . 'assets/js/quick-reply.js', true, WP_SMS_VERSION);
        wp_localize_script('wpsms-quick-reply', 'wpSmsQuickReplyTemplateVar', array(
                'restRootUrl' => esc_url_raw(rest_url()),
                'nonce'       => wp_create_nonce('wp_rest'),
                'senderID'    => $sms->from
            )
        );

        // Register main plugin style
        wp_register_style('wpsms-admin', WP_SMS_URL . 'assets/css/admin.css', true, WP_SMS_VERSION);

        /**
         * Whole setting page's assets
         */
        if (stristr($screen->id, 'wp-sms')) {
            wp_enqueue_style('wpsms-select2', WP_SMS_URL . 'assets/css/select2.min.css', true, WP_SMS_VERSION);
            wp_enqueue_script('wpsms-select2', WP_SMS_URL . 'assets/js/select2.min.js', true, WP_SMS_VERSION);
            wp_enqueue_script('wpsms-word-and-character-counter', WP_SMS_URL . 'assets/js/jquery.word-and-character-counter.min.js', true, WP_SMS_VERSION);
            wp_enqueue_script('wpsms-repeater', WP_SMS_URL . 'assets/js/jquery.repeater.min.js', true, WP_SMS_VERSION);
            wp_enqueue_script('wpsms-admin', WP_SMS_URL . 'assets/js/admin.js', true, WP_SMS_VERSION);

            wp_enqueue_style('wpsms-admin');

            if (is_rtl()) {
                wp_enqueue_style('wpsms-rtl', WP_SMS_URL . 'assets/css/rtl.css', true, WP_SMS_VERSION);
            }
        }

        /**
         * Dashboard widgets
         */
        if ($screen->id == 'dashboard') {
            wp_enqueue_style('wpsms-admin');
        }

        /**
         * Send SMS page's assets
         */
        if ($screen->id === 'toplevel_page_wp-sms') {
            if (!did_action('wp_enqueue_media')) {
                wp_enqueue_media();
            }
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

    /**
     * Dashboard glance plugin
     */
    public function dashboard_glance()
    {
        $subscribe = $this->db->get_var("SELECT COUNT(*) FROM {$this->tb_prefix}sms_subscribes");
        $credit    = get_option('wpsms_gateway_credit');

        echo "<li class='wpsms-subscribe-count'><a href='" . WP_SMS_ADMIN_URL . "admin.php?page=wp-sms-subscribers'>" . sprintf(__('%s Subscriber', 'wp-sms'), $subscribe) . "</a></li>";
        if (!is_object($credit)) {
            echo "<li class='wpsms-credit-count'><a href='" . WP_SMS_ADMIN_URL . "admin.php?page=wp-sms-settings&tab=web-service'>" . sprintf(__('%s SMS Credit', 'wp-sms'), $credit) . "</a></li>";
        }
    }

    /**
     * Administrator admin_menu
     */
    public function admin_menu()
    {
        $hook_suffix = array();

        // Incoming messages notification bubble
        $unreadMessagesCount = method_exists(\WPSmsTwoWay\Models\IncomingMessage::class, 'countOfUnreadMessages') ? \WPSmsTwoWay\Models\IncomingMessage::countOfUnreadMessages() : null;
        $notificationBubble  = $unreadMessagesCount ? sprintf(' <span class="awaiting-mod">%d</span>', $unreadMessagesCount) : '';

        add_menu_page(__('SMS', 'wp-sms'), __('SMS', 'wp-sms') . $notificationBubble, 'wpsms_sendsms', 'wp-sms', array($this, 'send_sms_callback'), 'dashicons-email-alt');
        $hook_suffix['send_sms'] = add_submenu_page('wp-sms', __('Send SMS', 'wp-sms'), __('Send SMS', 'wp-sms'), 'wpsms_sendsms', 'wp-sms', array($this, 'send_sms_callback'), 1);

        $hook_suffix['outbox'] = add_submenu_page('wp-sms', __('Outbox', 'wp-sms'), __('Outbox', 'wp-sms'), 'wpsms_outbox', 'wp-sms-outbox', array($this, 'outbox_callback'), 2);
        $hook_suffix['inbox']  = add_submenu_page('wp-sms', __('Inbox', 'wp-sms'), __('Inbox', 'wp-sms') . $notificationBubble, 'wpsms_inbox', 'wp-sms-inbox', array($this, 'inbox_callback'), 3);

        $hook_suffix['subscribers'] = add_submenu_page('wp-sms', __('Subscribers', 'wp-sms'), __('Subscribers', 'wp-sms'), 'wpsms_subscribers', 'wp-sms-subscribers', array($this, 'subscribers_callback'), 4);
        $hook_suffix['groups']      = add_submenu_page('wp-sms', __('Groups', 'wp-sms'), __('Groups', 'wp-sms'), 'wpsms_subscribers', 'wp-sms-subscribers-group', array($this, 'groups_callback'), 5);

        // Check GDPR compliance for Privacy menu
        if (isset($this->options['gdpr_compliance']) and $this->options['gdpr_compliance'] == 1) {
            $hook_suffix['privacy'] = add_submenu_page('wp-sms', __('Privacy', 'wp-sms'), __('Privacy', 'wp-sms'), 'manage_options', 'wp-sms-subscribers-privacy', array($this, 'privacy_callback'), 5);
        }

        add_submenu_page('wp-sms', __('Settings', 'wp-sms'), __('Settings', 'wp-sms'), 'wpsms_setting', 'wp-sms-settings', array($this->settings, 'render_settings'), 6);
        add_submenu_page('wp-sms', __('Add-Ons', 'wp-sms'), sprintf(__('%sAdd-Ons%s', 'wp-sms'), '<span style="color:#FF7600">', '</span>'), 'manage_options', 'wp-sms-add-ons', array($this, 'add_ons_callback'), 8);

        // Add styles to menu pages
        foreach ($hook_suffix as $menu => $hook) {

            // build the method name, for example outbox_assets
            $methodName = "{$menu}_assets";

            // Backward compatibility
            if (method_exists($this, $methodName)) {
                add_action("load-{$hook}", array($this, $methodName));
            }
        }
    }

    public function displayFlashNotice()
    {
        $notice = get_option('wpsms_flash_message', false);
        if ($notice) {
            delete_option('wpsms_flash_message');
            \WP_SMS\Admin\Helper::notice($notice['text'], $notice['model']);
        }
    }

    /**
     * Callback send sms page.
     */
    public function send_sms_callback()
    {
        $page = new SMS_Send();
        $page->render_page();
    }

    /**
     * Callback outbox page.
     */
    public function outbox_callback()
    {
        $page = new Outbox();
        $page->render_page();
    }

    /**
     *  Callback inbox page.
     */
    public function inbox_callback()
    {
        $page = new Inbox();
        $page->render_page();
    }

    /**
     * Callback subscribers page.
     */
    public function subscribers_callback()
    {
        // Subscribers class.
        require_once WP_SMS_DIR . 'includes/admin/subscribers/class-wpsms-subscribers.php';

        $page = new Subscribers();
        $page->render_page();
    }

    /**
     * Callback subscribers page.
     */
    public function groups_callback()
    {
        // Groups class.
        require_once WP_SMS_DIR . 'includes/admin/groups/class-wpsms-groups.php';

        $page = new Groups();
        $page->render_page();
    }

    /**
     * Callback subscribers page.
     */
    public function privacy_callback()
    {
        // Privacy class.
        require_once WP_SMS_DIR . 'includes/admin/privacy/class-wpsms-privacy.php';

        $page           = new Privacy();
        $page->pagehook = get_current_screen()->id;
        $page->render_page();
    }

    public function add_ons_callback()
    {
        $page = new AddOns();
        $page->init();
    }

    /**
     * Load send SMS page assets
     */
    public function send_sms_assets()
    {
        if (\WP_SMS\Version::pro_is_active()) {
            wp_enqueue_style('jquery-flatpickr', WP_SMS_URL . 'assets/css/flatpickr.min.css', true, WP_SMS_VERSION);
            wp_enqueue_script('jquery-flatpickr', WP_SMS_URL . 'assets/js/flatpickr.min.js', array('jquery'), WP_SMS_VERSION);
        }

        wp_register_script('wp-sms-send-page', WP_SMS_URL . 'assets/js/admin-send-sms.js', array('jquery'), WP_SMS_VERSION, true);
        wp_enqueue_script('wp-sms-send-page');
        wp_localize_script('wp-sms-send-page', 'WpSmsSendSmsTemplateVar', array(
            'restRootUrl'     => esc_url_raw(rest_url()),
            'nonce'           => wp_create_nonce('wp_rest'),
            'messageMsg'      => __('characters', 'wp-sms'),
            'currentDateTime' => current_datetime()->format("Y-m-d H:i:00"),
            'proIsActive'     => \WP_SMS\Version::pro_is_active(),
        ));
    }

    /**
     * Load outbox page assets
     */
    public function outbox_assets()
    {
        /**
         * Add per page option.
         */
        add_screen_option('per_page', array(
            'label'   => __('Number of items per page', 'wp-sms'),
            'default' => 20,
            'option'  => 'wp_sms_outbox_per_page',
        ));
    }

    /**
     * Load inbox page assets
     */
    public function inbox_assets()
    {
        /**
         * Add per page option.
         */
        add_screen_option('per_page', array(
            'label'   => __('Number of items per page', 'wp-sms'),
            'default' => 20,
            'option'  => 'wp_sms_inbox_per_page',
        ));
    }

    /**
     * Load subscribers page assets
     */
    public function subscribers_assets()
    {
        /**
         * Add per page option.
         */
        add_screen_option('per_page', array(
            'label'   => __('Number of items per page', 'wp-sms'),
            'default' => 20,
            'option'  => 'wp_sms_subscriber_per_page',
        ));

        wp_register_script('wp-sms-edit-subscriber', WP_SMS_URL . 'assets/js/edit-subscriber.js', array('jquery'), null, true);
        wp_enqueue_script('wp-sms-edit-subscriber');
        wp_localize_script('wp-sms-edit-subscriber', 'wp_sms_edit_subscribe_ajax_vars', array(
            'tb_show_url' => add_query_arg(array('action' => 'wp_sms_edit_subscriber'), admin_url('admin-ajax.php')),
            'tb_show_tag' => __('Edit Subscriber', 'wp-sms')
        ));
    }

    /**
     * Load groups page assets
     */
    public function groups_assets()
    {
        /**
         * Add per page option.
         */
        add_screen_option('per_page', array(
            'label'   => __('Number of items per page', 'wp-sms'),
            'default' => 20,
            'option'  => 'wp_sms_group_per_page',
        ));

        wp_register_script('wp-sms-edit-group', WP_SMS_URL . 'assets/js/edit-group.js', array('jquery'), null, true);
        wp_enqueue_script('wp-sms-edit-group');
        wp_localize_script('wp-sms-edit-group', 'wp_sms_edit_group_ajax_vars', array(
            'tb_show_url' => add_query_arg(array('action' => 'wp_sms_edit_group'), admin_url('admin-ajax.php')),
            'tb_show_tag' => __('Edit Group', 'wp-sms')
        ));
    }

    /**
     * Load privacy page assets
     */
    public function privacy_assets()
    {
        $pagehook = get_current_screen()->id;

        wp_enqueue_script('common');
        wp_enqueue_script('wp-lists');
        wp_enqueue_script('postbox');

        add_meta_box('privacy-meta-1', esc_html(get_admin_page_title()), array(Privacy::class, 'privacy_meta_html_gdpr'), $pagehook, 'side', 'core');
        add_meta_box('privacy-meta-2', __('Export User’s Data related to WP SMS', 'wp-sms'), array(Privacy::class, 'privacy_meta_html_export'), $pagehook, 'normal', 'core');
        add_meta_box('privacy-meta-3', __('Erase User’s Data related to WP SMS', 'wp-sms'), array(Privacy::class, 'privacy_meta_html_delete'), $pagehook, 'normal', 'core');
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
            $rate_url = 'http://wordpress.org/support/view/plugin-reviews/wp-sms?rate=5#postform';
            $links[]  = '<a href="' . $rate_url . '" target="_blank" class="wpsms-plugin-meta-link" title="' . __('Click here to rate and review this plugin on WordPress.org', 'wp-sms') . '">' . __('Rate this plugin', 'wp-sms') . '</a>';

            $newsletter_url = WP_SMS_SITE . '/newsletter';
            $links[]        = '<a href="' . $newsletter_url . '" target="_blank" class="wpsms-plugin-meta-link" title="' . __('Click here to rate and review this plugin on WordPress.org', 'wp-sms') . '">' . __('Subscribe to our Email Newsletter', 'wp-sms') . '</a>';
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
        if (isset($_GET['action'])) {
            if ($_GET['action'] == 'wpsms-hide-newsletter') {
                update_option('wpsms_hide_newsletter', true);
            }
        }

        if (!get_option('wpsms_hide_newsletter')) {
            add_action('wp_sms_settings_page', array($this, 'admin_newsletter'));
        }

        // Check exists require function
        if (!function_exists('wp_get_current_user')) {
            include(ABSPATH . "wp-includes/pluggable.php");
        }

        include_once WP_SMS_DIR . "includes/admin/export.php";

        // Add plugin caps to admin role
        if (is_admin() and is_super_admin()) {
            $this->add_cap();
        }
    }

    /**
     * Admin newsletter
     */
    public function admin_newsletter()
    {
        echo Helper::loadTemplate('admin/newsletter-form.php');
    }

    /**
     * Validate screen options on update.
     *
     * @param $status Screen option value. Default false to skip.
     * @param $option The option name.
     * @param $value The number of rows to use.
     * @return bool|int
     */
    public function set_screen_option($status, $option, $value)
    {
        if (in_array($option, array('wp_sms_subscriber_per_page'), true)) {
            return $value;
        }

        if (in_array($option, array('wp_sms_outbox_per_page'), true)) {
            return $value;
        }

        if (in_array($option, array('wp_sms_inbox_per_page'), true)) {
            return $value;
        }

        if (in_array($option, array('wp_sms_scheduled_per_page'), true)) {
            return $value;
        }

        if (in_array($option, array('wp_sms_group_per_page'), true)) {
            return $value;
        }

        return $status;
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
        if (isset($_GET['page']) && in_array($_GET['page'], array('wp-sms', 'wp-sms-outbox', 'wp-sms-inbox', 'wp-sms-scheduled', 'wp-sms-subscribers', 'wp-sms-subscribers-group', 'wp-sms-subscribers-privacy', 'wp-sms-settings', 'wp-sms-add-ons'))) {
            $classes .= ' sms_page_wp-sms';
        }

        return $classes;
    }

    public function do_output_buffer()
    {
        $tabs = array('wp-sms-subscribers-group', 'wp-sms-subscribers', 'wp-sms-scheduled', 'wp-sms-inbox', 'wp-sms-outbox');
        if (is_admin() and isset($_GET['page']) and in_array($_GET['page'], $tabs)) {
            ob_start();
        }
    }
}

new Admin();
