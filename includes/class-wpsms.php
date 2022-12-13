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

        // Legacy classes.
        $this->include('includes/class-wpsms-features.php');
        $this->include('includes/class-wpsms-notifications.php');
        $this->include('includes/class-wpsms-integrations.php');
        $this->include('includes/class-wpsms-gravityforms.php');
        $this->include('includes/class-wpsms-quform.php');
        $this->include('includes/class-wpsms-newsletter.php');
        $this->include('includes/class-wpsms-rest-api.php');
        $this->include('includes/class-wpsms-shortcode.php');
        $this->include('includes/admin/class-wpsms-version.php');

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

        $webhookManager = new \WP_SMS\Webhook\WebhookManager();
        $webhookManager->init();

        if (is_admin()) {
            // Admin legacy classes.
            $this->include('includes/admin/settings/class-wpsms-settings.php');
            $this->include('includes/admin/class-wpsms-admin.php');
            $this->include('includes/admin/class-wpsms-admin-helper.php');
            $this->include('includes/admin/outbox/class-wpsms-outbox.php');
            $this->include('includes/admin/inbox/class-wpsms-inbox.php');
            $this->include('includes/admin/privacy/class-wpsms-privacy-actions.php');
            $this->include('includes/admin/send/class-wpsms-send.php');
            $this->include('includes/admin/add-ons/class-add-ons.php');

            // Widgets
            $this->include('src/Widget/WidgetsManager.php');
            \WP_SMS\Widget\WidgetsManager::init();
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
}
