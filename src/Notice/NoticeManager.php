<?php

namespace WP_SMS\Notice;

class NoticeManager extends AbstractNotice
{
    protected static $instance = null;

    public function __construct()
    {
        add_action('admin_init', [$this, 'initStaticNotice']);
        add_action('admin_notices', array($this, 'displayFlashNotice'));
    }

    public static function getInstance()
    {
        null === self::$instance and self::$instance = new self;

        return self::$instance;
    }

    public function displayFlashNotice()
    {
        $notice = get_option('wpsms_flash_message', false);

        if ($notice) {
            delete_option('wpsms_flash_message');

            Notice::notice($notice['text'], $notice['model']);

            /**
             * @todo Remove this after replacing \WP_SMS\Admin\Helper::notice with Notice::notice in all plugins
             */
            //\WP_SMS\Admin\Helper::notice($notice['text'], $notice['model']);
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
        $this->render();
        $this->action();
    }

    private function registerStaticNotices()
    {
        $this->registerNotice(__('You need to configure the Mobile field option in General settings to send SMS to customers.', 'wp-sms'), true, 'admin.php?page=wp-sms-settings&tab=pro_woocommerce');
        $this->registerNotice(__('You need to configure the Mobile field option to use login with SMS functionality.', 'wp-sms'), true, 'admin.php?page=wp-sms-settings');
    }
}
