<?php

namespace WP_SMS\Api\V1;

use WP_REST_Server;
use WP_REST_Request;
use WP_SMS\RestApi;
use WP_SMS\Admin\LicenseManagement\ApiCommunicator;
use WP_SMS\Admin\LicenseManagement\LicenseHelper;
use WP_SMS\Admin\LicenseManagement\LicenseMigration;
use WP_SMS\Admin\LicenseManagement\Plugin\PluginHelper;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add-Ons REST API Controller
 */
class AddonsApi extends RestApi
{
    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
        parent::__construct();
    }

    /**
     * Register REST API routes
     */
    public function registerRoutes()
    {
        register_rest_route($this->namespace . '/v1', '/addons', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getAddons'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route($this->namespace . '/v1', '/addons/activate-license', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'activateLicense'],
                'permission_callback' => [$this, 'checkPermission'],
                'args'                => [
                    'license_key' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'addon_slug' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ],
        ]);

        register_rest_route($this->namespace . '/v1', '/addons/remove-license', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'removeLicense'],
                'permission_callback' => [$this, 'checkPermission'],
                'args'                => [
                    'addon_slug' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Check permission
     *
     * @return bool
     */
    public function checkPermission()
    {
        return current_user_can('wpsms_setting');
    }

    /**
     * Get all add-ons
     *
     * @param WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getAddons(WP_REST_Request $request)
    {
        try {
            // Migrate old licenses before fetching
            $apiCommunicator = new ApiCommunicator();
            $migration = new LicenseMigration($apiCommunicator);
            $migration->migrateOldLicenses();

            $plugins = PluginHelper::getRemotePlugins();
            $addons = [];

            // Get all licenses (valid + expired) for looking up per-addon
            $allLicenses = array_merge(
                LicenseHelper::getLicenses('valid'),
                LicenseHelper::getLicenses('license_expired')
            );

            foreach ($plugins as $plugin) {
                $slug = $plugin->getSlug();

                // Find all licenses that contain this addon
                $licenses = [];
                foreach ($allLicenses as $key => $license) {
                    if (!empty($license['products']) && in_array($slug, $license['products'])) {
                        $licenses[] = [
                            'masked_key' => str_repeat('•', max(0, strlen($key) - 4)) . substr($key, -4),
                            'status'     => $license['status'] ?? 'unknown',
                        ];
                    }
                }

                $addons[] = [
                    'slug'                => $slug,
                    'name'                => $plugin->getName(),
                    'description'         => $plugin->getDescription(),
                    'short_description'   => $plugin->getShortDescription(),
                    'icon'                => $plugin->getIcon(),
                    'version'             => $plugin->getVersion(),
                    'price'               => $plugin->getPrice(),
                    'label'               => $plugin->getLabel(),
                    'status'              => $plugin->getStatus(),
                    'status_label'        => $plugin->getStatusLabel(),
                    'licenses'            => $licenses,
                    'is_installed'        => $plugin->isInstalled(),
                    'is_activated'        => $plugin->isActivated(),
                    'is_update_available' => $plugin->isUpdateAvailable(),
                    'product_url'         => $plugin->getProductUrl(),
                    'documentation_url'   => $plugin->getDocumentationUrl(),
                    'changelog_url'       => $plugin->getChangelogUrl(),
                    'settings_url'        => $plugin->getSettingsUrl(),
                ];
            }

            return self::response(__('Add-ons retrieved successfully', 'wp-sms'), 200, [
                'addons' => $addons,
            ]);
        } catch (\Exception $e) {
            return self::response($e->getMessage(), 500);
        }
    }

    /**
     * Activate a license for an add-on
     *
     * @param WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function activateLicense(WP_REST_Request $request)
    {
        $licenseKey = $request->get_param('license_key');
        $addonSlug = $request->get_param('addon_slug');

        try {
            $apiCommunicator = new ApiCommunicator();
            $apiCommunicator->validateLicense($licenseKey, $addonSlug);

            return self::response(__('License activated successfully.', 'wp-sms'), 200);
        } catch (\Exception $e) {
            return self::response($e->getMessage(), 400);
        }
    }
    /**
     * Remove a license for an add-on
     *
     * @param WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function removeLicense(WP_REST_Request $request)
    {
        $addonSlug = $request->get_param('addon_slug');

        try {
            $licenseKey = LicenseHelper::getPluginLicense($addonSlug);

            if (empty($licenseKey)) {
                return self::response(__('No license found for this add-on.', 'wp-sms'), 404);
            }

            LicenseHelper::removeLicense($licenseKey);

            return self::response(__('License removed successfully.', 'wp-sms'), 200);
        } catch (\Exception $e) {
            return self::response($e->getMessage(), 400);
        }
    }
}

new AddonsApi();
