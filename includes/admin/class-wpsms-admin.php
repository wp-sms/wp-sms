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
        $this->initFeedback();

        // Add Actions
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        add_action('admin_bar_menu', array($this, 'admin_bar'), 40);
        add_action('dashboard_glance_items', array($this, 'dashboard_glance'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('init', array($this, 'do_output_buffer'));

        // Add Filters
        add_filter('plugin_row_meta', array($this, 'meta_links'), 0, 2);
        add_filter('set-screen-option', array($this, 'set_screen_option'), 10, 3);
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
        if (stristr($screen->id, 'wp-sms') or $screen->base == 'post' or $screen->id == 'edit-wpsms-command' or $screen->id == 'edit-sms-campaign') {
            $text = sprintf(
                __('Please rate <strong>WP SMS</strong> <a href="%2$s" title="%3$s" target="_blank">★★★★★</a> on <a href="%2$s" target="_blank">WordPress.org</a> to help us spread the word. Thank you!', 'wp-sms'),
                esc_html__('WP SMS', 'wp-sms'),
                'https://wordpress.org/support/plugin/wp-sms/reviews/?filter=5#new-post',
                esc_html__('Rate WP SMS', 'wp-sms')
            );
        }
        return $text;
    }

    public function wpsms_update_footer($content)
    {
        $screen = get_current_screen();
        if (stristr($screen->id, 'wp-sms') or $screen->base == 'post' or $screen->id == 'edit-wpsms-command' or $screen->id == 'edit-sms-campaign') {
            global $wp_version;
            $plugin_data    = get_plugin_data(WP_SMS_DIR . 'wp-sms.php');
            $plugin_version = $plugin_data['Version'];
            $content        = sprintf('<p id="footer-upgrade" class="alignright">%s | %s %s</p>',
                esc_html__('WordPress', 'wp-sms') . ' ' . esc_html($wp_version),
                esc_html($plugin_data['Name']),
                esc_html($plugin_version)
            );
        }
        return $content;
    }

    /**
     * Include admin assets
     */
    public function admin_assets()
    {
        global $sms;
        $nonce = wp_create_nonce('wp_rest');

        // Register admin-bar.css for whole admin area
        if (is_admin_bar_showing()) {
            wp_register_style('wpsms-admin-bar', WP_SMS_URL . 'assets/css/admin-bar.css', [], WP_SMS_VERSION);
            wp_enqueue_style('wpsms-admin-bar');
        }

        $screen = get_current_screen();

        // Register main plugin style
        wp_register_style('wpsms-admin', WP_SMS_URL . 'assets/css/admin.css', [], WP_SMS_VERSION);

        /**
         * Whole setting page's assets
         */
        if (stristr($screen->id, 'wp-sms') or $screen->base == 'post' or $screen->id == 'edit-wpsms-command' or $screen->id == 'edit-sms-campaign' or $screen->id == 'woocommerce_page_wc-orders') {
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('wp-color-picker');

            if (stristr($screen->id, 'wp-sms')) {
                wp_enqueue_style('jquery-flatpickr', WP_SMS_URL . 'assets/css/flatpickr.min.css', [], WP_SMS_VERSION);
                wp_enqueue_script('jquery-flatpickr', WP_SMS_URL . 'assets/js/flatpickr.min.js', array('jquery'), WP_SMS_VERSION, false);
                
                wp_enqueue_script('wpsms-repeater', WP_SMS_URL . 'assets/js/jquery.repeater.min.js', [], WP_SMS_VERSION, false);
                // tooltip
                wp_enqueue_style('wpsms-tooltip', WP_SMS_URL . 'assets/css/tooltipster.bundle.css', [], WP_SMS_VERSION);
                wp_enqueue_script('wpsms-tooltip', WP_SMS_URL . 'assets/js/tooltipster.bundle.js', [], WP_SMS_VERSION, false);
            }

            if (!did_action('wp_enqueue_media')) {
                wp_enqueue_media();
            }

            wp_enqueue_style('wpsms-admin');

            if (is_rtl()) {
                wp_enqueue_style('wpsms-rtl', WP_SMS_URL . 'assets/css/rtl.css', [], WP_SMS_VERSION);
            }
        }

        $order_id = 0;

        // Backward compatibility with new custom WooCommerce order table.
        if (isset($_GET['page']) && $_GET['page'] == 'wc-orders' && isset($_GET['id'])) {
            $order_id = sanitize_text_field($_GET['id']);
        } elseif (isset($_GET['post']) && $_GET['post']) {
            $order_id = sanitize_text_field($_GET['post']);
        }
        $customer_mobile = \WP_SMS\Helper::getWooCommerceCustomerNumberByOrderId($order_id);

        wp_enqueue_style('wpsms-select2', WP_SMS_URL . 'assets/css/select2.min.css', [], WP_SMS_VERSION);
        wp_enqueue_script('wpsms-select2', WP_SMS_URL . 'assets/js/select2.min.js', [], WP_SMS_VERSION, false);
        wp_enqueue_script('wpsms-word-and-character-counter', WP_SMS_URL . 'assets/js/jquery.word-and-character-counter.min.js', [], WP_SMS_VERSION, false);


        $admin_script_deps = ['jquery', 'wp-color-picker', 'jquery-ui-spinner'];
        $statsWidget       = new \WP_SMS\Widget\Widgets\StatsWidget();

        wp_enqueue_script('wpsms-admin', WP_SMS_URL . 'assets/js/admin.min.js', $admin_script_deps, WP_SMS_VERSION, false);
        wp_localize_script('wpsms-admin', 'WP_Sms_Admin_Dashboard_Object', apply_filters('wp_sms_stats_widget_data', []));
        wp_localize_script('wpsms-admin', 'WP_Sms_Admin_Object', array(
                'restUrls'        => array(
                    'sendSms' => get_rest_url(null, 'wpsms/v1/send'),
                    'users'   => get_rest_url(null, 'wp/v2/users')
                ),
                'ajaxUrls'        => array(
                    'export'              => \WP_SMS\Controller\ExportAjax::url(),
                    'uploadSubscriberCsv' => \WP_SMS\Controller\UploadSubscriberCsv::url(),
                    'importSubscriberCsv' => \WP_SMS\Controller\ImportSubscriberCsv::url(),
                    'privacyData'         => \WP_SMS\Controller\PrivacyDataAjax::url(),
                    'subscribe'           => \WP_SMS\Controller\SubscriberFormAjax::url(),
                    'group'               => \WP_SMS\Controller\GroupFormAjax::url(),
                ),
                'lang'            => array(
                    'checkbox_label' => esc_html__('Send SMS?', 'wp-sms'),
                    'checkbox_desc'  => __('The SMS will be sent if the <b>Note to the customer</b> is selected.', 'wp-sms')
                ),
                'tag'             => array(
                    'subscribe' => esc_html__('Edit Subscriber', 'wp-sms'),
                    'group'     => esc_html__('Edit Group', 'wp-sms')
                ),
                'nonce'           => $nonce,
                'senderID'        => $sms->from,
                'receiver'        => $customer_mobile,
                'order_id'        => $order_id,
                'siteName'        => get_bloginfo('name'),
                'messageMsg'      => esc_html__('characters', 'wp-sms'),
                'currentDateTime' => WP_SMS_CURRENT_DATE,
                'proIsActive'     => \WP_SMS\Version::pro_is_active(),
            )
        );

        /**
         * Dashboard widgets
         */
        if ($screen->id == 'dashboard') {
            wp_localize_script('wpsms-admin', 'WP_Sms_Admin_Dashboard_Object', apply_filters('wp_sms_stats_widget_data', $statsWidget->getLocalizationData()));
            wp_enqueue_style('wpsms-admin');
        }

        /**
         * Contact Form 7 SMS Notification Tab
         */
        if ($screen->id == 'toplevel_page_wpcf7') {
            wp_enqueue_style('wpsms-select2', WP_SMS_URL . 'assets/css/select2.min.css', [], WP_SMS_VERSION);
            wp_enqueue_script('wpsms-select2', WP_SMS_URL . 'assets/js/select2.min.js', [], WP_SMS_VERSION, false);
            wp_enqueue_style('wpsms-admin');
            wp_enqueue_script('wpsms-admin', WP_SMS_URL . 'assets/js/admin.min.js', [], WP_SMS_VERSION, false);
        }
    }

    /**
     * Admin bar plugin
     */
    public function admin_bar()
    {
        global $wp_admin_bar;

        if (is_super_admin() && is_admin_bar_showing() && current_user_can('wpsms_sendsms')) {
            $credit = get_option('wpsms_gateway_credit');
            if (isset($this->options['account_credit_in_menu']) and !is_object($credit)) {
                $wp_admin_bar->add_menu(array(
                    'id'    => 'wp-credit-sms',
                    'title' => '<span class="ab-icon"></span>' . $credit,
                    'href'  => WP_SMS_ADMIN_URL . 'admin.php?page=wp-sms-settings'
                ));
            }
        }

        if (current_user_can('wpsms_sendsms')) {
            $wp_admin_bar->add_menu(array(
                'id'     => 'wp-send-sms',
                'parent' => 'new-content',
                'title'  => esc_html__('SMS', 'wp-sms'),
                'href'   => WP_SMS_ADMIN_URL . 'admin.php?page=wp-sms'
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
        echo "<li class='wpsms-subscribe-count'><a href='" . WP_SMS_ADMIN_URL . "admin.php?page=wp-sms-subscribers'>" . sprintf(esc_html__('%s Subscriber', 'wp-sms'), esc_html($subscribe)) . "</a></li>"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        if (!is_object($credit)) {
            // translators: %s: SMS credit 
            echo "<li class='wpsms-credit-count'><a href='" . WP_SMS_ADMIN_URL . "admin.php?page=wp-sms-settings&tab=web-service'>" . sprintf(esc_html__('%s SMS Credit', 'wp-sms'), esc_html($credit)) . "</a></li>"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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

        add_menu_page(esc_html__('SMS', 'wp-sms'), esc_html__('SMS', 'wp-sms') . $notificationBubble, 'wpsms_sendsms', 'wp-sms', array($this, 'send_sms_callback'), 'dashicons-email-alt');
        $hook_suffix['send_sms'] = add_submenu_page('wp-sms', esc_html__('Send SMS', 'wp-sms'), esc_html__('Send SMS', 'wp-sms'), 'wpsms_sendsms', 'wp-sms', array($this, 'send_sms_callback'), 1);

        $hook_suffix['outbox'] = add_submenu_page('wp-sms', esc_html__('Outbox', 'wp-sms'), esc_html__('Outbox', 'wp-sms'), 'wpsms_outbox', 'wp-sms-outbox', array($this, 'outbox_callback'), 2);
        $hook_suffix['inbox']  = add_submenu_page('wp-sms', esc_html__('Inbox', 'wp-sms'), esc_html__('Inbox', 'wp-sms') . $notificationBubble, 'wpsms_inbox', 'wp-sms-inbox', array($this, 'inbox_callback'), 3);

        $hook_suffix['subscribers'] = add_submenu_page('wp-sms', esc_html__('Subscribers', 'wp-sms'), esc_html__('Subscribers', 'wp-sms'), 'wpsms_subscribers', 'wp-sms-subscribers', array($this, 'subscribers_callback'), 4);
        $hook_suffix['groups']      = add_submenu_page('wp-sms', esc_html__('Groups', 'wp-sms'), esc_html__('Groups', 'wp-sms'), 'wpsms_subscribers', 'wp-sms-subscribers-group', array($this, 'groups_callback'), 5);

        // Check GDPR compliance for Privacy menu
        if (isset($this->options['gdpr_compliance']) and $this->options['gdpr_compliance'] == 1) {
            $hook_suffix['privacy'] = add_submenu_page('wp-sms', esc_html__('Privacy', 'wp-sms'), esc_html__('Privacy', 'wp-sms'), 'wpsms_setting', 'wp-sms-subscribers-privacy', array($this, 'privacy_callback'), 5);
        }

        add_submenu_page('wp-sms', esc_html__('Settings', 'wp-sms'), esc_html__('Settings', 'wp-sms'), 'wpsms_setting', 'wp-sms-settings', function () {
            return $this->settings->render_settings('general', array('title' => esc_html__('Settings', 'wp-sms')));
        }, 6);
        add_submenu_page('wp-sms', esc_html__('Integrations', 'wp-sms'), esc_html__('Integrations', 'wp-sms'), 'wpsms_setting', 'wp-sms-integrations', function () {
            return (new SettingsIntegration)->render_settings('contact_form7',
                array('header_template' => 'header.php', 'title' => esc_html__('Integrations', 'wp-sms'))
            );
        }, 7);

        // translators: %1$s: Starting span tag, %2$s: Closing span tag 
        add_submenu_page('wp-sms', esc_html__('Add-Ons', 'wp-sms'), sprintf(esc_html__('%1$sAdd-Ons%2$s', 'wp-sms'), '<span style="color:#FF7600">', '</span>'), 'manage_options', 'wp-sms-add-ons', array($this, 'add_ons_callback'), 8);

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
     * Load outbox page assets
     */
    public function outbox_assets()
    {
        /**
         * Add per page option.
         */
        add_screen_option('per_page', array(
            'label'   => esc_html__('Number of items per page', 'wp-sms'),
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
            'label'   => esc_html__('Number of items per page', 'wp-sms'),
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
            'label'   => esc_html__('Number of items per page', 'wp-sms'),
            'default' => 20,
            'option'  => 'wp_sms_subscriber_per_page',
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
            'label'   => esc_html__('Number of items per page', 'wp-sms'),
            'default' => 20,
            'option'  => 'wp_sms_group_per_page',
        ));


    }

    /**
     * Load privacy page assets
     */
    public function privacy_assets()
    {
        wp_enqueue_script('common');
        wp_enqueue_script('wp-lists');
        wp_enqueue_script('postbox');

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
            $rate_url = 'https://wordpress.org/support/view/plugin-reviews/wp-sms/?filter=5#new-post';
            $links[]  = '<a href="' . $rate_url . '" target="_blank" class="wpsms-plugin-meta-link" title="' . esc_html__('Click here to rate and review this plugin on WordPress.org', 'wp-sms') . '">' . esc_html__('Rate this plugin', 'wp-sms') . '</a>';
            $links[]  = '<a href="https://dashboard.mailerlite.com/forms/421827/86962232715379904/share" target="_blank" class="wpsms-plugin-meta-link" title="' . esc_html__('Click here to rate and review this plugin on WordPress.org', 'wp-sms') . '">' . esc_html__('Subscribe to our Email Newsletter', 'wp-sms') . '</a>';
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
     * Init FeedbackBird widget a third-party service to get feedbacks from users
     *
     * @url https://feedbackbird.io
     *
     * @return void
     */
    private function initFeedback()
    {
        add_action('admin_enqueue_scripts', function () {
            $screen = get_current_screen();

            if (stristr($screen->id, 'wp-sms')) {
                wp_enqueue_script('feedbackbird-widget', 'https://cdn.jsdelivr.net/gh/feedbackbird/assets@master/wp/app.js?uid=01H1V6WNG62AXA1JV5X8X76XZR', [], false, false); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.NoExplicitVersion
                wp_add_inline_script('feedbackbird-widget', sprintf('var feedbackBirdObject = %s;', wp_json_encode([
                    'user_email' => function_exists('wp_get_current_user') ? wp_get_current_user()->user_email : '',
                    'platform'   => 'wordpress-admin',
                    'config'     => [
                        'color'         => '#EF771F',
                        'button'        => esc_html__('Feedback', 'wp-sms'),
                        'subtitle'      => esc_html__('Feel free to share your thoughts!', 'wp-sms'),
                        'opening_style' => 'modal',
                    ],
                    'meta'       => [
                        'php_version'    => PHP_VERSION,
                        'active_plugins' => array_map(function ($plugin, $pluginPath) {
                            return [
                                'name'    => $plugin['Name'],
                                'version' => $plugin['Version'],
                                'status'  => is_plugin_active($pluginPath) ? 'active' : 'deactivate',
                            ];
                        }, get_plugins(), array_keys(get_plugins())),
                    ]
                ])));

                add_filter('script_loader_tag', function ($tag, $handle, $src) {
                    if ('feedbackbird-widget' === $handle) {
                        return preg_replace('/^<script /i', '<script type="module" crossorigin="crossorigin" ', $tag);
                    }
                    return $tag;
                }, 10, 3);
            }
        });
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
        if (isset($_GET['page']) && in_array($_GET['page'], array('wp-sms', 'wp-sms-outbox', 'wp-sms-inbox', 'wp-sms-scheduled', 'wp-sms-subscribers', 'wp-sms-subscribers-group', 'wp-sms-subscribers-privacy', 'wp-sms-settings', 'wp-sms-integrations', 'wp-sms-add-ons'))) {
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
