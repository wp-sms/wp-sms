<?php

use WP_SMS\BackgroundProcess\Async\DataMigrationProcess;
use WP_SMS\BackgroundProcess\Async\RemoteRequestAsync;
use WP_SMS\BackgroundProcess\Async\SchemaMigrationProcess;
use WP_SMS\BackgroundProcess\Async\TableOperationProcess;
use WP_SMS\BackgroundProcess\Queues\RemoteRequestQueue;
use WP_SMS\Blocks\BlockAssetsManager;
use WP_SMS\Controller\ControllerManager;
use WP_SMS\Notice\NoticeManager;
use WP_SMS\Services\CronJobs\CronJobManager;
use WP_SMS\Services\Database\Managers\MigrationHandler;
use WP_SMS\Services\Formidable\FormidableManager;
use WP_SMS\Services\Forminator\ForminatorManager;
use WP_SMS\Services\MessageButton\MessageButtonManager;
use WP_SMS\Services\WooCommerce\WooCommerceCheckout;
use WP_SMS\Shortcode\ShortcodeManager;
use WP_SMS\User\MobileFieldManager;
use WP_SMS\Webhook\WebhookManager;
use WP_SMS\Widget\WidgetsManager;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

class WP_SMS
{
    const MIN_PHP_VERSION = '7.4';
    /**
     * Plugin instance.
     *
     * @see get_instance()
     * @type object
     */
    protected static $instance = null;

    /**
     * @var RemoteRequestAsync $remoteRequestAsync
     */
    private $remoteRequestAsync;

    /**
     * @var RemoteRequestQueue $remoteRequestQueue
     */
    private $remoteRequestQueue;

    /**
     * @var $backgroundProcess
     */
    private $backgroundProcess;


    public function __construct()
    {
        /*
         * Plugin Loaded Action
         */
        add_action('plugins_loaded', array($this, 'plugin_setup'));

        require_once WP_SMS_DIR . 'includes/class-wpsms-install.php';
        require_once WP_SMS_DIR . 'includes/class-wpsms-uninstall.php';

        register_activation_hook(WP_SMS_DIR . 'wp-sms.php', array($this, 'activate'));
        register_deactivation_hook(WP_SMS_DIR . 'wp-sms.php', array($this, 'deactivate'));

        add_action('admin_init', [$this, 'handlePhpVersionNoticeDismissal']);
        add_action('admin_init', [$this, 'maybeShowPhpVersionNotice']);
    }

    /**
     * Install And Upgrade plugin
     */
    public function activate($network_wide)
    {
        $class = new \WP_SMS\Install();
        $class->install($network_wide);
    }

    /**
     * Deactivate & Uninstall plugin
     */
    public function deactivate()
    {
        $class = new \WP_SMS\Uninstall();
        $class->deactivate();
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
     */
    public function plugin_setup()
    {
        add_action('init', array($this, 'init'));

        $this->includes();
        $this->setupBackgroundProcess();
        MigrationHandler::init();

    }

    /**
     * @return void
     */
    private function setupBackgroundProcess()
    {
        $this->registerBackgroundProcess(RemoteRequestAsync::class, 'remote_request_async');
        $this->registerBackgroundProcess(RemoteRequestQueue::class, 'remote_request_queue');
        $this->registerBackgroundProcess(DataMigrationProcess::class, 'data_migration_process');
        $this->registerBackgroundProcess(SchemaMigrationProcess::class, 'schema_migration_process');
        $this->registerBackgroundProcess(TableOperationProcess::class, 'table_operations_process');

    }

    /**
     * @param $className
     * @param $processKey
     * @return void
     */
    private function registerBackgroundProcess($className, $processKey)
    {
        if (class_exists($className)) {
            $this->backgroundProcess[$processKey] = new $className();
        }
    }

    /**
     * @param $processKey
     * @return mixed
     */
    public function getBackgroundProcess($processKey)
    {
        return $this->backgroundProcess[$processKey];
    }

    public function init()
    {
        $this->loadTextDomain();
        $this->initGateway();
    }

    /**
     * Load plugin text domain.
     */
    private function loadTextDomain()
    {
        // Compatibility with WordPress < 5.0
        if (function_exists('determine_locale')) {
            $locale = apply_filters('plugin_locale', determine_locale(), 'wp-sms');

            unload_textdomain('wp-sms', true);
            load_textdomain('wp-sms', WP_LANG_DIR . '/wp-sms-' . $locale . '.mo');
        }

        load_plugin_textdomain('wp-sms', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    private function initGateway()
    {
        $GLOBALS['sms'] = wp_sms_initial_gateway();
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
        // Autoloader
        require_once WP_SMS_DIR . "vendor/autoload.php";

        // Third-party libraries
        $this->include('includes/libraries/wp-background-processing/wp-async-request.php');
        $this->include('includes/libraries/wp-background-processing/wp-background-process.php');

        add_action('init', function () {
            $mobileFieldManager = new MobileFieldManager();
            $mobileFieldManager->init();
        });

        // Legacy classes.
        $this->include('includes/class-wpsms-features.php');
        $this->include('includes/class-wpsms-notifications.php');
        $this->include('includes/class-wpsms-integrations.php');
        $this->include('includes/class-wpsms-gravityforms.php');
        $this->include('includes/class-wpsms-quform.php');
        $this->include('includes/class-wpsms-newsletter.php');
        $this->include('includes/class-wpsms-rest-api.php');
        $this->include('includes/admin/class-wpsms-version.php');

        // Initializing managers.
        (new CronJobManager())->init();
        (new ControllerManager())->init();
        (new WebhookManager())->init();
        (new BlockAssetsManager())->init();
        (new WooCommerceCheckout())->init();
        (new MessageButtonManager())->init();
        (new FormidableManager())->init();
        (new ForminatorManager())->init();
        (new ShortcodeManager())->init();

        if (is_admin()) {
            // Admin legacy classes.
            $this->include('includes/admin/settings/class-wpsms-settings.php');
            $this->include('includes/admin/settings/class-wpsms-settings-integration.php');
            $this->include('includes/admin/class-wpsms-admin.php');
            $this->include('includes/admin/class-wpsms-admin-helper.php');
            $this->include('includes/admin/outbox/class-wpsms-outbox.php');
            $this->include('includes/admin/inbox/class-wpsms-inbox.php');
            $this->include('includes/admin/send/class-wpsms-send.php');
            $this->include('includes/admin/add-ons/class-add-ons.php');

            WidgetsManager::init();
            NoticeManager::getInstance();
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

    public function maybeShowPhpVersionNotice()
    {
        if (version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '<')) {
            return;
        }

        $noticeManager = NoticeManager::getInstance();

        // Build dismissible message with optional link
        $message = __('Starting with WP SMS v7.1, the plugin requires PHP version 7.2 or higher. Support for PHP 5.6 has been officially dropped. To learn more about this change and why it’s important, please read our <a href="#" target="_blank">blog post</a>.', 'wp-sms');

        // Register using static notice system with dismiss support
        $noticeManager->registerNotice('php_version_warning', $message, true);
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
     * @return NoticeManager
     */
    public function notice()
    {
        return NoticeManager::getInstance();
    }

    /**
     * @return RemoteRequestAsync
     */
    public function getRemoteRequestAsync()
    {
        return $this->remoteRequestAsync;
    }

    /**
     * @return RemoteRequestQueue
     */
    public function getRemoteRequestQueue()
    {
        return $this->remoteRequestQueue;
    }
}
