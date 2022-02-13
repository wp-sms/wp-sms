<?php
/**
 * Plugin Name:     WP-SMS Two-way
 * Plugin URI:      https://wp-sms-pro.com/
 * Plugin Prefix:   WPSmsTwoWay
 * Description:     Powerful integration version of WP-SMS for WooCommerce
 * Author:          VeronaLabs
 * Author URI:      https://wp-sms-pro.com/two-way
 * Text Domain:     wp-sms-two-way
 * Domain Path:     /languages
 * Version:         1.0.0
 */

if (file_exists(dirname(__FILE__) . '/vendor/autoload.php')) {
    require dirname(__FILE__) . '/vendor/autoload.php';
}

use Backyard\Application;
use Backyard\Plugin;
use Backyard\Redirects\AdminNotice;
use Backyard\Utils\Singleton;
use WPSmsTwoWay\Services\Migration;
use WPSmsTwoWay\Services\Logger\ExceptionLogger;

/**
 * Class WPSmsTwoWayPlugin
 *
 * @package WPSmsTwoWayPlugin
 */
class WPSmsTwoWayPlugin extends Singleton
{
    /**
     * @var Backyard\Plugin
     */
    private $plugin;

    /**
     * Plugin's service providers
     *
     * @var array[]
     */
    private $providers = [

        //External providers
        \Backyard\Database\DatabaseServiceProvider::class,
        \Backyard\Blade\BladeServiceProvider::class,
        \Backyard\Logger\LoggerServiceProvider::class,
        \Backyard\Redirects\RedirectServiceProvider::class,

        //Internal provider
        \WPSmsTwoWay\Services\Logger\LoggerServiceProvider::class,
        \WPSmsTwoWay\Services\RestApi\RestApiServiceProvider::class,
        \WPSmsTwoWay\Services\Webhook\WebhookServiceProvider::class,
        \WPSmsTwoWay\Services\Command\CommandServiceProvider::class,
        \WPSmsTwoWay\Services\Action\ActionServiceProvider::class,
        \WPSmsTwoWay\Services\Gateway\GatewayServiceProvider::class,
        \WPSmsTwoWay\Services\Setting\SettingServiceProvider::class,
    ];

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct()
    {
        $this->plugin = Application::get()->loadPlugin(__DIR__, __FILE__, 'config');
        $this->init();
    }

    /**
     * Initialize the plugin
     *
     * @return void
     */
    public function init()
    {
        try {
            if (!class_exists(\WP_SMS::class)) {
                throw new Exception('WP SMS Two Way: Please install/activate WP SMS');
            }

            $this->_loadServiceProviders();

            $this->plugin->boot(function (Plugin $plugin) {
                $plugin->loadPluginTextDomain();
            });
            $this->plugin->onActivation(function () {
                Migration::migrate();
            });
        } catch (Exception $e) {
            add_action('admin_notices', function () use ($e) {
                AdminNotice::permanent(['type' => 'error', 'message' => $e->getMessage()]);
            });

            //Log the exceptions
            error_log($e->getMessage());
            if ($this->plugin->has(ExceptionLogger::class)) {
                $this->plugin->get(ExceptionLogger::class)->withName('plugin_init')->error($e);
            }
        }
    }

    /**
     * Load Plugin's service providers
     *
     * @return void
     */
    private function _loadServiceProviders()
    {
        foreach ($this->providers as $provider) {
            $this->plugin->addServiceProvider($provider);
        }
    }

    /**
     * Get WPSmsTwoWay plugin instance
     *
     * @return Backyard\Plugin
     */
    public function getPlugin()
    {
        return $this->plugin;
    }
}

/**
 * Return the single instance of wpsms-two-way plugin
 *
 * @return WPSmsTwoWayPlugin
 */
function WPSmsTwoWay()
{
    return WPSmsTwoWayPlugin::get();
}


add_action('plugins_loaded', function () {
    WPSmsTwoWay();
	 Migration::refresh();
}, 0);
