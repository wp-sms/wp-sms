<?php

use WP_SMS\Admin\Widget\WidgetsManager;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

class WP_SMS
{
    /**
     * Plugin instance.
     *
     * @see get_instance()
     * @type object
     */
    protected static $instance = null;

    public function __construct()
    {
        /*
         * Plugin Loaded Action
         */
        add_action('plugins_loaded', array($this, 'plugin_setup'));

        /**
         * Install And Upgrade plugin
         */
        require_once WP_SMS_DIR . 'includes/class-wpsms-install.php';

        register_activation_hook(WP_SMS_DIR . 'wp-sms.php', array('\WP_SMS\Install', 'install'));
    }

    /**
     * Access this plugin’s working instance
     *
     * @wp-hook plugins_loaded
     * @return  object of this class
     * @since   2.2.0
     */
    public static function get_instance()
    {
        null === self::$instance and self::$instance = new self;

        return self::$instance;
    }

    /**
     * Constructors plugin Setup
     *
     * @param Not param
     */
    public function plugin_setup()
    {
        // Load text domain
        add_action('init', array($this, 'load_textdomain'));

        $this->includes();
    }

    /**
     * Load plugin textdomain.
     *
     * @since 1.0.0
     */
    public function load_textdomain()
    {
        // Compatibility with WordPress < 5.0
        if (function_exists('determine_locale')) {
            $locale = apply_filters('plugin_locale', determine_locale(), 'wp-sms');

            unload_textdomain('wp-sms');
            load_textdomain('wp-sms', WP_LANG_DIR . '/wp-sms-' . $locale . '.mo');
        }

        load_plugin_textdomain('wp-sms', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /*
     * Include file
     */
    private function include($file)
    {
        $file_path = WP_SMS_DIR . $file;

        if (file_exists($file_path)) {
            require_once $file_path;
        }
    }

    /**
     * Includes plugin files
     *
     * @param Not param
     */
    public function includes()
    {
        // Utility classes.
        $this->include('src/Helper.php');
        $this->include('src/Utils/CsvHelper.php');
        $this->include('src/Report/EmailReportGenerator.php');

        // MobileFieldHandler
        $this->include('src/User/MobileFieldHandler/DefaultFieldHandler.php');
        $this->include('src/User/MobileFieldHandler/WooCommerceAddMobileFieldHandler.php');
        $this->include('src/User/MobileFieldHandler/WooCommerceUsePhoneFieldHandler.php');
        $this->include('src/User/MobileFieldHandler/WordPressMobileFieldHandler.php');
        $this->include('src/User/RegisterUserViaPhone.php');
        $this->include('src/User/MobileFieldManager.php');
        add_action('init', function () {
            $mobileFieldManager = new \WP_SMS\User\MobileFieldManager();
            $mobileFieldManager->init();
        });

        // Notification classes
        $this->include('src/Notification/Notification.php');
        $this->include('src/Notification/Handler/DefaultNotification.php');
        $this->include('src/Notification/Handler/WooCommerceOrderNotification.php');
        $this->include('src/Notification/Handler/WooCommerceCouponNotification.php');
        $this->include('src/Notification/Handler/WooCommerceCustomerNotification.php');
        $this->include('src/Notification/Handler/WooCommerceProductNotification.php');
        $this->include('src/Notification/Handler/WordPressPostNotification.php');
        $this->include('src/Notification/Handler/WordPressUserNotification.php');
        $this->include('src/Notification/Handler/WordPressCommentNotification.php');
        $this->include('src/Notification/Handler/SubscriberNotification.php');
        $this->include('src/Notification/Handler/CustomNotification.php');
        $this->include('src/Notification/Handler/AwesomeSupportTicketNotification.php');
        $this->include('src/Notification/NotificationFactory.php');

        // Legacy classes.
        $this->include('includes/class-wpsms-features.php');
        $this->include('includes/class-wpsms-notifications.php');
        $this->include('includes/class-wpsms-integrations.php');
        $this->include('includes/class-wpsms-gravityforms.php');
        $this->include('includes/class-wpsms-quform.php');
        $this->include('includes/class-wpsms-newsletter.php');
        $this->include('includes/class-wpsms-rest-api.php');
        $this->include('includes/admin/class-wpsms-version.php');

        // Newsletter
        $this->include('src/Subscriber/SubscriberManager.php');
        $subscriberManager = new \WP_SMS\Subscriber\SubscriberManager();
        $subscriberManager->init();

        // Cron Jobs
        $this->include('src/CronJobs/WeeklyReport.php');
        $this->include('src/CronJobs/CronJobManager.php');
        $cronJobManager = new \WP_SMS\CronJob\CronJobManager();
        $cronJobManager->init();

        // Blocks
        $this->include('src/BlockAbstract.php');
        $this->include('src/Blocks/SubscribeBlock.php');
        $this->include('src/BlockAssetsManager.php');

        $blockManager = new \WP_SMS\Blocks\BlockAssetsManager();
        $blockManager->init();

        // Controllers
        $this->include('src/Controller/AjaxControllerAbstract.php');
        $this->include('src/Controller/SubscriberFormAjax.php');
        $this->include('src/Controller/GroupFormAjax.php');
        $this->include('src/Controller/ExportAjax.php');
        $this->include('src/Controller/UploadSubscriberCsv.php');
        $this->include('src/Controller/PrivacyDataAjax.php');
        $this->include('src/Controller/ImportSubscriberCsv.php');
        $this->include('src/Controller/ControllerManager.php');

        $controllerManager = new \WP_SMS\Controller\ControllerManager();
        $controllerManager->init();

        // Webhooks
        $this->include('src/Webhook/WebhookFactory.php');
        $this->include('src/Webhook/WebhookAbstract.php');
        $this->include('src/Webhook/WebhookManager.php');
        $this->include('src/Webhook/NewSubscriberWebhook.php');
        $this->include('src/Webhook/NewSmsWebhook.php');
        $this->include('src/Webhook/NewIncomingSmsWebhook.php');

        $webhookManager = new \WP_SMS\Webhook\WebhookManager();
        $webhookManager->init();

        // SmsOtp
        $this->include('src/SmsOtp/Exceptions/OtpLimitExceededException.php');
        $this->include('src/SmsOtp/Exceptions/TooManyAttemptsException.php');
        $this->include('src/SmsOtp/Exceptions/InvalidArgumentException.php');
        $this->include('src/SmsOtp/Generator.php');
        $this->include('src/SmsOtp/Verifier.php');
        $this->include('src/SmsOtp/SmsOtp.php');

        // Services
        $this->include('src/Services/WooCommerce/WooCommerceCheckout.php');
        $wooCommerceCheckout = new \WP_SMS\Services\WooCommerce\WooCommerceCheckout();
        $wooCommerceCheckout->init();
        $this->include('src/Services/WooCommerce/OrderViewManager.php');

        // Shortcode
        $this->include('src/Shortcode/ShortcodeManager.php');
        $this->include('src/Shortcode/SubscriberShortcode.php');

        $shortcodeManager = new \WP_SMS\Shortcode\ShortcodeManager();
        $shortcodeManager->init();

        if (is_admin()) {
            // Admin legacy classes.
            $this->include('includes/admin/settings/class-wpsms-settings.php');
            $this->include('includes/admin/class-wpsms-admin.php');
            $this->include('includes/admin/class-wpsms-admin-helper.php');
            $this->include('includes/admin/outbox/class-wpsms-outbox.php');
            $this->include('includes/admin/inbox/class-wpsms-inbox.php');
            $this->include('includes/admin/send/class-wpsms-send.php');
            $this->include('includes/admin/add-ons/class-add-ons.php');

            // Widgets
            $this->include('src/Widget/WidgetsManager.php');
            \WP_SMS\Widget\WidgetsManager::init();

            // Notices
            $this->include('src/Notice/AbstractNotice.php');
            $this->include('src/Notice/NoticeManager.php');
            \WP_SMS\Notice\NoticeManager::getInstance();
        }

        if (!is_admin()) {
            // Front Class.
            $this->include('includes/class-front.php');
        }

        // API class.
        $this->include('includes/api/v1/class-wpsms-api-newsletter.php');
        $this->include('includes/api/v1/class-wpsms-api-send.php');
        $this->include('includes/api/v1/class-wpsms-api-webhook.php');
        $this->include('includes/api/v1/class-wpsms-api-credit.php');
    }

    /**
     * @return \WP_SMS\Pro\Scheduled
     */
    public function scheduled()
    {
        return new \WP_SMS\Pro\Scheduled();
    }

    /**
     * @return \WP_SMS\Newsletter
     */
    public function newsletter()
    {
        return new \WP_SMS\Newsletter();
    }

    /**
     * @return \WP_SMS\Notification\NotificationFactory
     */
    public function notification()
    {
        return new \WP_SMS\Notification\NotificationFactory();
    }

    /**
     * @return \WP_SMS\Notice\NoticeManager
     */
    public function notice()
    {
        return \WP_SMS\Notice\NoticeManager::getInstance();
    }
}
