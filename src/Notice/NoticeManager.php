<?php

namespace WP_SMS\Notice;

use WP_SMS\Admin\LicenseManagement\LicenseHelper;
use WP_SMS\Admin\LicenseManagement\Plugin\PluginHandler;
use WP_SMS\Components\View;
use WP_SMS\Option;
use WP_SMS\Helper;

class NoticeManager extends AbstractNotice
{
    protected static $instance = null;
    protected $options;

    public function __construct()
    {
        $this->options = Option::getOptions();

        // Static notices
        add_action('admin_init', [$this, 'initStaticNotice']);
        add_action('admin_notices', array($this, 'displayStaticNotices'));

        // Flash notices
        add_action('admin_notices', array($this, 'displayFlashNotices'));

        // Addons license notices
        add_action('wp_sms_pro_before_content_render', [$this, 'displayProNotice']);

        // Woocommerce Pro
        add_action('wp_sms_woocommerce_pro_before_content_render', [$this, 'displayWoocommerceProLicenseNotice']);

        // Two-Way
        add_action('wp_sms_two_way_before_content_render', [$this, 'displayTwoLicenseNotice']);
        add_action('wp_sms_addon_two_way_before_content_render', [$this, 'displayTwoLicenseNotice']);

        // Fluent
        add_action('wp_sms_addon_fluent_crm_before_content_render', [$this, 'displayFluentLicenseNotice']);
        add_action('wp_sms_addon_fluent_forms_before_content_render', [$this, 'displayFluentLicenseNotice']);
        add_action('wp_sms_addon_fluent_support_before_content_render', [$this, 'displayFluentLicenseNotice']);

        // Membership
        add_action('wp_sms_addon_paid_membership_pro_before_content_render', [$this, 'displayMembershipLicenseNotice']);
        add_action('wp_sms_addon_simple_membership_before_content_render', [$this, 'displayMembershipLicenseNotice']);

        // Booking
        add_action('wp_sms_addon_booking_integrations_woo_bookings_before_content_render', [$this, 'displayBookingLicenseNotice']);
        add_action('wp_sms_addon_booking_integrations_bookingpress_before_content_render', [$this, 'displayBookingLicenseNotice']);
        add_action('wp_sms_addon_booking_integrations_booking_calendar_before_content_render', [$this, 'displayBookingLicenseNotice']);
        add_action('wp_sms_addon_booking_integrations_woo_appointments_before_content_render', [$this, 'displayBookingLicenseNotice']);
    }

    public static function getInstance()
    {
        null === self::$instance and self::$instance = new self;

        return self::$instance;
    }

    /**
     * Init static (pre defined) notice functionality
     *
     * @return void
     */
    public function initStaticNotice()
    {
        $this->registerStaticNotices();

        do_action('wp_sms_before_register_notice', $this);
        $this->action();
    }

    /**
     * Register our static notices here
     */
    private function registerStaticNotices()
    {
        $mobileFieldStatus = Option::getOption('add_mobile_field');

        if ($mobileFieldStatus !== 'add_mobile_field_in_wc_billing' && $mobileFieldStatus !== 'use_phone_field_in_wc_billing') {
            $this->registerNotice('woocommerce_mobile_field', __('You need to configure the Mobile field option in General settings to send SMS to customers.', 'wp-sms'), true, 'admin.php?page=wp-sms-integrations&tab=pro_woocommerce');
        }

        if (!$mobileFieldStatus or $mobileFieldStatus == 'disable') {
            $this->registerNotice('login_mobile_field', __('You need to configure the Mobile field option to use login with SMS functionality.', 'wp-sms'), true, 'admin.php?page=wp-sms-settings&tab=pro_wordpress');
        }

        if (version_compare(PHP_VERSION, '7.2', '<')) {
            $current_version = PHP_VERSION;
            $message         = sprintf(
                __('
            <strong>WP SMS notice – PHP upgrade required</strong><br>
            Your site is running PHP %s. upcoming WP SMS 7.1 requires PHP 7.2 or higher. 
            Please upgrade your server’s PHP version before installing the update. 
            <a href="https://wp-sms-pro.com/33155/version-7-1/" target="_blank">More details</a>.
        ', 'wp-sms'),
                esc_html($current_version)
            );

            $this->registerNotice('php_version_warning', wp_kses_post($message), true);
        }

        // translators: %s: Newsletter link
        $this->registerNotice('marketing_newsletter', sprintf(__('Stay informed and receive exclusive offers, <a href="%s" target="_blank">Subscribe to our newsletter here</a>!', 'wp-sms'), 'https://dashboard.mailerlite.com/forms/421827/86962232715379904/share'), true, 'admin.php?page=wp-sms-settings');
    }

    /**
     * Display Static Notices
     */
    public function displayStaticNotices()
    {
        $nonce   = wp_create_nonce('wp_sms_notice');
        $notices = get_option($this->staticNoticeOption, []);
        if (!is_array($notices)) {
            $notices = [];
        }

        foreach ($this->notices as $id => $notice) {
            if (isset($notices[$id]) && $notices[$id]) {
                continue;
            }

            $link = $this->generateNoticeLink($id, $notice['url'], $nonce);

            if (!$notice['url'] or (basename($_SERVER['REQUEST_URI']) == $notice['url'])) {
                Helper::notice($notice['message'], 'warning', $notice['dismiss'], $link);
            }
        }
    }

    /**
     * Display Flash Notices
     */
    public function displayFlashNotices()
    {
        $notice = get_option($this->flashNoticeOption, false);

        if ($notice) {
            delete_option($this->flashNoticeOption);
            Helper::notice($notice['text'], $notice['model']);
        }
    }

    /**
     * Display license notice for WP SMS Pro core addon
     */
    public function displayProNotice()
    {
        $slug           = 'wp-sms-pro';
        $plugin_handler = new PluginHandler();

        if (!LicenseHelper::isPluginLicenseValid($slug) && $plugin_handler->isPluginActive($slug)) {
            View::load("components/lock-sections/notice-inactive-license-addon");
        }

        if (!LicenseHelper::isPluginLicenseValid($slug) && !$plugin_handler->isPluginActive($slug)) {
            View::load("components/lock-sections/unlock-all-in-one-addon");
        }
    }


    /**
     * Display license notice for WooCommerce Pro addon
     */
    public function displayWoocommerceProLicenseNotice()
    {
        $slug = 'wp-sms-woocommerce-pro';

        if (!LicenseHelper::isPluginLicenseValid($slug)) {
            View::load("components/lock-sections/notice-inactive-license-addon");
        }
    }

    /**
     * Display license notice for Two-Way addon
     */
    public function displayTwoLicenseNotice()
    {
        $slug = 'wp-sms-two-way';

        if (!LicenseHelper::isPluginLicenseValid($slug)) {
            View::load("components/lock-sections/notice-inactive-license-addon");
        }
    }

    /**
     * Display license notice for Fluent addons (CRM, Forms, Support)
     */
    public function displayFluentLicenseNotice()
    {
        $slug = 'wp-sms-fluent-integrations';

        if (!LicenseHelper::isPluginLicenseValid($slug)) {
            View::load("components/lock-sections/notice-inactive-license-addon");
        }
    }

    /**
     * Display license notice for Membership addons (Paid Memberships Pro, Simple Membership)
     */
    public function displayMembershipLicenseNotice()
    {
        $slug = 'wp-sms-membership-integrations';

        if (!LicenseHelper::isPluginLicenseValid($slug)) {
            View::load("components/lock-sections/notice-inactive-license-addon");
        }
    }

    /**
     * Display license notice for Booking integrations (WooCommerce Bookings, BookingPress, Booking Calendar, Woo Appointments)
     */
    public function displayBookingLicenseNotice()
    {
        $slug = 'wp-sms-booking-integrations';

        if (!LicenseHelper::isPluginLicenseValid($slug)) {
            View::load("components/lock-sections/notice-inactive-license-addon");
        }
    }

}
