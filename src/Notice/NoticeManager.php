<?php

namespace WP_SMS\Notice;

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
            $dismissed = array_key_exists($id, $notices);
            $link      = $this->generateNoticeLink($id, $notice['url'], $nonce);

            if (!$notice['url'] or (basename($_SERVER['REQUEST_URI']) == $notice['url'] && !$dismissed)) {
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
}
