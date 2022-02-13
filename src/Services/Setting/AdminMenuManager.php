<?php

namespace WPSmsTwoWay\Services\Setting;

use Backyard\Plugin;
use VeronaLabs\Updater\UpdaterChecker;
use WPSmsTwoWay\Services\Setting\InboxPage;
use WPSmsTwoWay\Services\Gateway\GatewayManager;
use WPSmsTwoWay\Services\Webhook\Webhook;

/**
 * Class AdminMenuManager
 * @package WPSmsTwoWay\Setting
 */
class AdminMenuManager
{
    public const MENU_SLUG = 'wpsms-two-way';

    /**
     * Plugin's single instance
     *
     * @var Backyard\Plugin
     */
    private $application;

    /**
     * AdminMenuManager constructor.
     */
    public function __construct(Plugin $application)
    {
        $this->application = $application;

        $this->registerMenus();
        $this->enqueueSettingsAssets();
        $this->addTwoWaySettingPage();
        $this->overrideInboxPage();
        $this->addLicenseToMainPlugin();
    }

    /**
     * Enqueue admin assets
     *
     * @return void
     */
    public function enqueueSettingsAssets()
    {
        add_action('admin_enqueue_scripts', function ($resetTokenUrl) {
            $plugin = WPsmsTwoWay()->getPlugin();

            wp_enqueue_style('wpsms-tw-main-styles', $this->application->getUrl() . '/assets/css/styles.css');

            wp_enqueue_script('wpsms-tw-main-script', $this->application->getUrl() . '/assets/js/main.js', ['jquery']);
            wp_localize_script('wpsms-tw-main-script', 'WPSmsTwoWayAdmin', [
                'routes' =>[
                    'resetToken' => [
                        'href'  => $plugin->get(Webhook::class)->getResetTokenRoute()->getUrl(),
                        'nonce' => wp_create_nonce($plugin->get(Webhook::class)->getResetTokenRoute()->getNonceHandle()),
                    ]
                ],
                'nonce_field_name' => $plugin->get('route')::NONCE_FIELD_NAME,
                'wp_nonce' => wp_create_nonce('wp_rest')
            ]);
        });
    }

    /**
     * Register admin menus
     *
     * @return void
     */
    public function registerMenus()
    {
        add_action('admin_menu', function () {
            add_menu_page('SMS Two Way', 'SMS Two Way', 'manage_options', self::MENU_SLUG, [$this, 'renderMainPage'], 'dashicons-email-alt');
        }, 11);
    }

    /**
     * Render main page
     *
     * This function exists only for convenience
     *
     * @return void
     */
    public function renderMainPage()
    {
    }

    /**
     * Override Inbox(Incoming Messages) page in wp-sms main plugin
     *
     * @return void
     */
    public function overrideInboxPage()
    {
        $plugin = WPSmsTwoWay()->getPlugin();
        add_filter('wp_sms_admin_inbox_render_callback', function () use ($plugin) {
            return function () use ($plugin) {
                echo $plugin->blade('pages.inbox', [
                    'table' => $plugin->get(InboxPage::class),
                ]);
            };
        });
    }

    /**
     * Add two-way tab to wpsms settings page
     *
     * @return void
     */
    public function addTwoWaySettingPage()
    {
        $plugin = WPSmsTwoWay()->getPlugin();

        add_filter('wp_sms_addon_two_way_settings', function ($tabs) use ($plugin) {
            return (new TwoWaySettingPage)->exportFields();
        });
    }

    /**
     * Add license filed to the main plugin (WPSMS)
     *
     * @return void
     */
    public function addLicenseToMainPlugin()
    {
        add_filter('wp_sms_addons', function ($addOns) {
            $addOns['wp-sms-two-way'] = 'WP-SMS Two Way';
            return $addOns;
        });
    }

    /**
     * @return SettingUpdateChecker
     */
    public function pluginUpdateChecker()
    {
        return new UpdaterChecker([
            'plugin_slug'  => 'wp-sms-two-way',
            'website_url'  => 'https://wp-sms-pro.com',
            'license_key'  => get_option('wpsmstwoway_general_license'),
            'plugin_path'  => wp_normalize_path(__FILE__),
            'setting_page' => admin_url('admin.php?page=wp-sms-two-way-settings')
        ]);
    }

    /**
     * @param $fields
     */
    public function checkLicenseValidity($fields)
    {
        $license       = isset($_POST['wpsmstwoway_general_license']) ? esc_attr($_POST['wpsmstwoway_general_license']) : '';
        $licenseServer = $this->application->config('wp_sms_website') . '/wp-json/plugins/v1/validate';

        /*
         * Check License with license server
         */
        $response = wp_remote_get(add_query_arg([
            'plugin-name' => 'wp-sms-two-way',
            'license_key' => $license,
            'website'     => get_bloginfo('url'),
        ], $licenseServer));

        if (is_wp_error($response) === false) {
            $result = json_decode($response['body'], true);

            if (isset($result['status']) and $result['status'] == 200) {
                update_option('wpsmstwoway_general_license', true);
                return;
            }
        }

        update_option('wpsmstwoway_general_license_status', false);
    }
}
