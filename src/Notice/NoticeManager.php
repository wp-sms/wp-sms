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
        add_action('wp_sms_settings_page', array($this, 'displayStaticNotices'));

        // Flash notices
        add_action('admin_notices', array($this, 'displayFlashNotices'));
    }

    public static function getInstance()
    {
        null === self::$instance and self::$instance = new self;

        return self::$instance;
    }

    /**
     * Display Static Notices
     */
    public function displayStaticNotices()
    {
        $nonce = wp_create_nonce('wp_sms_notice');

        foreach ($this->notices as $id => $notice) {
            $dismissed = array_key_exists($id, get_option('wpsms_notices') ? get_option('wpsms_notices') : []);
            $link      = $this->generateNoticeLink($id, $notice['url'], $nonce);

            if (basename($_SERVER['REQUEST_URI']) == $notice['url'] && !$dismissed && $this->options['add_mobile_field'] == 'disable') {
                Helper::notice($notice['message'], 'warning', true, $link);
            }
        }
    }

    /**
     * Display Flash Notices
     */
    public function displayFlashNotices()
    {
        $notice = get_option('wpsms_flash_message', false);

        if ($notice) {
            delete_option('wpsms_flash_message');
            Helper::notice($notice['text'], $notice['model']);
        }
    }

    /**
     * Init static (pre defined) notice functionality
     *
     * @return void
     */
    public function initStaticNotice()
    {
        $this->registerStaticNotices();
        $this->action();
    }

    /**
     * Register our static notices here
     */
    private function registerStaticNotices()
    {
        $this->registerNotice('woocommerce_mobile_field', __('You need to configure the Mobile field option in General settings to send SMS to customers.', 'wp-sms'), true, 'admin.php?page=wp-sms-settings&tab=pro_woocommerce');
        $this->registerNotice('login_mobile_field', __('You need to configure the Mobile field option to use login with SMS functionality.', 'wp-sms'), true, 'admin.php?page=wp-sms-settings&tab=pro_wordpress');
        $this->registerNotice('marketing_newsletter', sprintf(__('Stay informed and receive exclusive offers - Subscribe to our newsletter <a href="%s" target="_blank">here</a>!', 'wp-sms'), 'https://dashboard.mailerlite.com/forms/421827/86962232715379904/share'), true, 'admin.php?page=wp-sms-settings');
    }
}
