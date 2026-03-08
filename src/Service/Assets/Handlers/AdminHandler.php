<?php

namespace WP_SMS\Service\Assets\Handlers;

use WP_SMS\Abstracts\BaseAssets;
use WP_SMS\Controller\LicenseManagerAjax;
use WP_SMS\Utils\Request;

if (!defined('ABSPATH')) exit;

class AdminHandler extends BaseAssets
{
    public function __construct()
    {
        $this->pluginUrl    = WP_SMS_URL;
        $this->pluginDir    = WP_SMS_DIR;
        $this->assetVersion = WP_SMS_VERSION;

        $this->setContext('admin');
        $this->setAssetDir('public');

        add_action('admin_enqueue_scripts', [$this, 'styles']);
        add_action('admin_enqueue_scripts', [$this, 'scripts']);
    }

    /**
     * Enqueue admin styles.
     *
     * @return void
     */
    public function styles()
    {
        // Admin bar style for the whole admin area
        if (is_admin_bar_showing()) {
            wp_register_style('wpsms-admin-bar', $this->getUrl('css/admin-bar.css'), [], $this->getVersion());
            wp_enqueue_style('wpsms-admin-bar');
        }

        $screen = get_current_screen();

        // Global admin style
        wp_enqueue_style('admin-global', $this->getUrl('css/admin-global.css'), [], $this->getVersion());

        // Register main plugin style
        wp_register_style('wpsms-admin', $this->getUrl('css/admin.css'), [], $this->getVersion());

        // Plugin page styles
        if (
            str_contains($screen->id, 'wsms') ||
            str_contains($screen->id, 'wp-sms') ||
            $screen->base === 'post' ||
            in_array($screen->id, ['edit-wpsms-command', 'edit-sms-campaign', 'woocommerce_page_wc-orders', 'plugins'])
        ) {
            wp_enqueue_style('wp-color-picker');

            if (stristr($screen->id, 'wsms') || stristr($screen->id, 'wp-sms')) {
                wp_enqueue_style('jquery-flatpickr', $this->getUrl('css/flatpickr.min.css'), [], $this->getVersion());
                wp_enqueue_style('wpsms-tooltip', $this->getUrl('css/tooltipster.bundle.css'), [], $this->getVersion());
            }

            wp_enqueue_style('wpsms-admin');

            if (is_rtl()) {
                wp_enqueue_style('wpsms-rtl', $this->getUrl('css/rtl.css'), [], $this->getVersion());
            }
        }

        // Select2 on all admin pages
        wp_enqueue_style('wpsms-select2', $this->getUrl('css/select2.min.css'), [], $this->getVersion());

        // Dashboard widget style
        if ($screen->id == 'dashboard') {
            wp_enqueue_style('wpsms-admin');
        }

        // Contact Form 7 page
        if ($screen->id == 'toplevel_page_wpcf7') {
            wp_enqueue_style('wpsms-select2', $this->getUrl('css/select2.min.css'), [], $this->getVersion());
            wp_enqueue_style('wpsms-admin');
        }
    }

    /**
     * Enqueue admin scripts.
     *
     * @param string $hook The current admin page hook.
     * @return void
     */
    public function scripts(string $hook = '')
    {
        global $sms;

        $nonce = wp_create_nonce('wp_rest');

        // Register global variables
        wp_register_script(
            'wp-sms-global-script',
            $this->getUrl('js/global.js'),
            [],
            $this->getVersion(),
            true
        );

        $list = [
            'i18n'           => $this->getTranslations(),
            'admin_url'      => admin_url(),
            'ajax_url'       => LicenseManagerAjax::url(),
            'rest_api_nonce' => wp_create_nonce('wp_rest')
        ];

        if (!empty(Request::get('license_key'))) {
            $list['license_key'] = Request::get('license_key');
        }

        wp_localize_script('wp-sms-global-script', 'wpsms_global', $list);

        $screen = get_current_screen();

        // Plugin page scripts
        if (
            str_contains($screen->id, 'wsms') ||
            str_contains($screen->id, 'wp-sms') ||
            $screen->base === 'post' ||
            in_array($screen->id, ['edit-wpsms-command', 'edit-sms-campaign', 'woocommerce_page_wc-orders', 'plugins'])
        ) {
            wp_enqueue_script('wp-color-picker');

            if (stristr($screen->id, 'wsms') || stristr($screen->id, 'wp-sms')) {
                wp_enqueue_script('jquery-flatpickr', $this->getUrl('js/flatpickr.min.js'), ['jquery'], $this->getVersion(), false);
                wp_enqueue_script('wpsms-repeater', $this->getUrl('js/jquery.repeater.min.js'), [], '1.2.2', false);
                wp_enqueue_script('wpsms-tooltip', $this->getUrl('js/tooltipster.bundle.js'), [], $this->getVersion(), false);
            }

            if (!did_action('wp_enqueue_media')) {
                wp_enqueue_media();
            }
        }

        // Select2 on all admin pages
        wp_enqueue_script('wpsms-select2', $this->getUrl('js/select2.min.js'), [], $this->getVersion(), false);

        // Main admin script
        $order_id = 0;

        // Backward compatibility with new custom WooCommerce order table.
        if (isset($_GET['page']) && $_GET['page'] == 'wc-orders' && isset($_GET['id'])) {
            $order_id = sanitize_text_field($_GET['id']);
        } elseif (isset($_GET['post']) && $_GET['post']) {
            $order_id = sanitize_text_field($_GET['post']);
        }

        $customer_mobile = \WP_SMS\Helper::getWooCommerceCustomerNumberByOrderId($order_id);

        $admin_script_deps = ['jquery', 'wp-color-picker', 'jquery-ui-spinner', 'wp-sms-global-script'];
        $statsWidget       = new \WP_SMS\Widget\Widgets\StatsWidget();

        wp_enqueue_script('wpsms-admin', $this->getUrl('js/admin.min.js'), $admin_script_deps, $this->getVersion(), false);
        wp_localize_script('wpsms-admin', 'WP_Sms_Admin_Dashboard_Object', apply_filters('wp_sms_stats_widget_data', []));
        wp_localize_script('wpsms-admin', 'WP_Sms_Admin_Object', [
            'restUrls'        => [
                'sendSms' => get_rest_url(null, 'wpsms/v1/send'),
                'users'   => get_rest_url(null, 'wp/v2/users')
            ],
            'ajaxUrls'        => [
                'export'                   => \WP_SMS\Controller\ExportAjax::url(),
                'uploadSubscriberCsv'      => \WP_SMS\Controller\UploadSubscriberCsv::url(),
                'importSubscriberCsv'      => \WP_SMS\Controller\ImportSubscriberCsv::url(),
                'privacyData'              => \WP_SMS\Controller\PrivacyDataAjax::url(),
                'subscribe'                => \WP_SMS\Controller\SubscriberFormAjax::url(),
                'group'                    => \WP_SMS\Controller\GroupFormAjax::url(),
                'RecipientCountsAjax'      => \WP_SMS\Controller\RecipientCountsAjax::url(),
                'UserRolesMobileCountAjax' => \WP_SMS\Controller\UserRolesMobileCountAjax::url(),
            ],
            'lang'            => [
                'checkbox_label' => esc_html__('Send SMS?', 'wp-sms'),
                'checkbox_desc'  => __('The SMS will be sent if the <b>Note to the customer</b> is selected.', 'wp-sms')
            ],
            'tag'             => [
                'subscribe' => esc_html__('Edit Subscriber', 'wp-sms'),
                'group'     => esc_html__('Edit Group', 'wp-sms')
            ],
            'nonce'           => $nonce,
            'senderID'        => $sms->from,
            'receiver'        => $customer_mobile,
            'order_id'        => $order_id,
            'siteName'        => get_bloginfo('name'),
            'messageMsg'      => esc_html__('characters', 'wp-sms'),
            'currentDateTime' => WP_SMS_CURRENT_DATE,
            'proIsActive'     => \WP_SMS\Version::pro_is_active(),
        ]);

        // Dashboard widgets
        if ($screen->id == 'dashboard') {
            wp_localize_script('wpsms-admin', 'WP_Sms_Admin_Dashboard_Object', apply_filters('wp_sms_stats_widget_data', $statsWidget->getLocalizationData()));
        }

        // Contact Form 7 page
        if ($screen->id == 'toplevel_page_wpcf7') {
            wp_enqueue_script('wpsms-admin', $this->getUrl('js/admin.min.js'), [], $this->getVersion(), false);
        }
    }

    /**
     * Returns an array of translations for script localization.
     *
     * @return array
     */
    private function getTranslations()
    {
        return [
            'more_detail'                  => __('View Details', 'wp-sms'),
            'reload'                       => __('Reload', 'wp-sms'),
            'online_users'                 => __('Online Visitors', 'wp-sms'),
            'Realtime'                     => __('Realtime', 'wp-sms'),
            'visitors'                     => __('Visitors', 'wp-sms'),
            'visits'                       => __('Views', 'wp-sms'),
            'today'                        => __('Today', 'wp-sms'),
            'yesterday'                    => __('Yesterday', 'wp-sms'),
            'week'                         => __('Last 7 days', 'wp-sms'),
            'this-week'                    => __('This week', 'wp-sms'),
            'last-week'                    => __('Last week', 'wp-sms'),
            'month'                        => __('Last 30 days', 'wp-sms'),
            'this-month'                   => __('This month', 'wp-sms'),
            'last-month'                   => __('Last month', 'wp-sms'),
            '7days'                        => __('Last 7 days', 'wp-sms'),
            '30days'                       => __('Last 30 days', 'wp-sms'),
            '60days'                       => __('Last 60 days', 'wp-sms'),
            '90days'                       => __('Last 90 days', 'wp-sms'),
            '6months'                      => __('Last 6 months', 'wp-sms'),
            'year'                         => __('Last 12 months', 'wp-sms'),
            'this-year'                    => __('This year (Jan-Today)', 'wp-sms'),
            'last-year'                    => __('Last year', 'wp-sms'),
            'total'                        => __('Total', 'wp-sms'),
            'daily_total'                  => __('Daily Total', 'wp-sms'),
            'date'                         => __('Date', 'wp-sms'),
            'time'                         => __('Time', 'wp-sms'),
            'browsers'                     => __('Browsers', 'wp-sms'),
            'rank'                         => __('#', 'wp-sms'),
            'flag'                         => __('Country Flag', 'wp-sms'),
            'country'                      => __('Country', 'wp-sms'),
            'visitor_count'                => __('Visitors', 'wp-sms'),
            'id'                           => __('ID', 'wp-sms'),
            'title'                        => __('Page', 'wp-sms'),
            'link'                         => __('Page Link', 'wp-sms'),
            'address'                      => __('Domain Address', 'wp-sms'),
            'word'                         => __('Search Term', 'wp-sms'),
            'browser'                      => __('Visitor\'s Browser', 'wp-sms'),
            'city'                         => __('Visitor\'s City', 'wp-sms'),
            'ip_hash'                      => __('IP Address/Hash', 'wp-sms'),
            'referring_site'               => __('Referring Site', 'wp-sms'),
            'hits'                         => __('Views', 'wp-sms'),
            'agent'                        => __('User Agent', 'wp-sms'),
            'platform'                     => __('Operating System', 'wp-sms'),
            'version'                      => __('Browser/OS Version', 'wp-sms'),
            'page'                         => __('Visited Page', 'wp-sms'),
            'privacy_compliant'            => __('Your WP SMS settings are privacy-compliant.', 'wp-sms'),
            'non_privacy_compliant'        => __('Your WP SMS settings are not privacy-compliant. Please update your settings.', 'wp-sms'),
            'no_result'                    => __('No recent data available.', 'wp-sms'),
            'published'                    => __('Published', 'wp-sms'),
            'author'                       => __('Author', 'wp-sms'),
            'view_detailed_analytics'      => __('View Detailed Analytics', 'wp-sms'),
            'enable_now'                   => __('Enable Now', 'wp-sms'),
            'receive_weekly_email_reports' => __('Receive Weekly Email Reports', 'wp-sms'),
            'close'                        => __('Close', 'wp-sms'),
            'previous_period'              => __('Previous period', 'wp-sms'),
            'view_content'                 => __('View Content', 'wp-sms'),
            'downloading'                  => __('Downloading', 'wp-sms'),
            'activated'                    => __('Activated', 'wp-sms'),
            'active'                       => __('Active', 'wp-sms'),
            'activating'                   => __('Activating', 'wp-sms'),
            'already_installed'            => __('Already installed', 'wp-sms'),
            'installed'                    => __('Installed', 'wp-sms'),
            'failed'                       => __('Failed', 'wp-sms'),
            'retry'                        => __('Retry', 'wp-sms'),
            'redirecting'                  => __('Redirecting... Please wait', 'wp-sms'),
            'update_license'               => __('Update License', 'wp-sms'),
            'select_groups'                => __('Please select the group(s) ...', 'wp-sms'),
            'no_results_found'             => __('No results found', 'wp-sms'),
            'fix_highlight'                => __('Please fix the highlighted field(s) below.', 'wp-sms'),
            'search'                       => __('Search ...', 'wp-sms'),
            'users_with_number'            => __('Users have the mobile number.', 'wp-sms')
        ];
    }
}
