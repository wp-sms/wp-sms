<?php

namespace WP_SMS\Notice;

use WP_SMS\Option;

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
            $tab = '';

            // @todo: update the condition logic.
            if (strpos($notice['url'], 'tab=') !== false) {
                $tab = substr($notice['url'], strpos($notice['url'], 'tab=') + 4);
            }

            $dismissed = array_key_exists($id, get_option('wpsms_notices') ? get_option('wpsms_notices') : []);
            $link      = $this->generateNoticeLink($id, $notice['url'], $nonce);

            if (isset($_GET['tab']) && $_GET['tab'] == $tab && !$dismissed && $this->options['add_mobile_field'] == 'disable') {
                Notice::notice($notice['message'], 'warning', true, $link);
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
            Notice::notice($notice['text'], $notice['model']);
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
    }
}
