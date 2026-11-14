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
                    'masked_key' => [
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'force'      => [
                        'required' => false,
                        'type'     => 'boolean',
                        'default'  => false,
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

                $needs = $plugin->isActivated() ? null : LicenseHelper::missingDependency($slug);

                // Find all licenses that contain this addon
                $licenses = [];
                foreach ($allLicenses as $key => $license) {
                    if (!empty($license['products']) && in_array($slug, $license['products'])) {
                        $licenses[] = [
                            'masked_key' => str_repeat('•', max(0, strlen($key) - 4)) . substr($key, -4),
                            'status'     => $license['status'] ?? 'unknown',
                            'sku'        => $license['sku'] ?? null,
                            'covers'     => is_array($license['products'] ?? null) ? count($license['products']) : 1,
                            'valid_until' => $license['valid_until'] ?? null,
                            'max_domains' => $license['max_domains'] ?? null,
                            // The renew link is needed once the key has expired or is about to (30 days).
                            'renewal'    => (($license['status'] ?? '') === 'license_expired' || self::expiresSoon($license['valid_until'] ?? null)) ? ($license['renewal'] ?? null) : null,
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
                    'needs'               => $needs,
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
                'addons'  => $addons,
                'summary' => LicenseHelper::getLicenseSummary(),
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
        $licenseKey = sanitize_text_field((string) $request->get_param('license_key'));
        $addonSlug  = sanitize_text_field((string) $request->get_param('addon_slug'));

        // The same key entered twice is not an error, and it is not a new activation either.
        $stored = LicenseHelper::getLicenses('all');
        if ($licenseKey !== '' && $addonSlug === '' && isset($stored[$licenseKey]) && ($stored[$licenseKey]['status'] ?? '') === 'valid') {
            return self::response(__('This license is already active on this site.', 'wp-sms'), 200, ['already' => true]);
        }

        try {
            $apiCommunicator = new ApiCommunicator();
            // No add-on slug: any valid key is accepted and unlocks whatever products it includes.
            $apiCommunicator->validateLicense($licenseKey, $addonSlug ?: false);

            return self::response(__('License activated successfully.', 'wp-sms'), 200);
        } catch (\WP_SMS\Exceptions\LicenseException $e) {
            // Give the page enough to explain what went wrong and where to go next.
            return self::response($e->getMessage(), 400, [
                'status'  => $e->getStatus(),
                'renewal' => $e->getRenewal(),
                'support_url' => $e->getSupportUrl(),
            ]);
        } catch (\Exception $e) {
            return self::response($e->getMessage(), 400);
        }
    }
    /**
     * Does a key run out within the next 30 days?
     *
     * @param string|null $validUntil A date such as 2027-11-06.
     * @return bool
     */
    private static function expiresSoon($validUntil)
    {
        $time = $validUntil ? strtotime($validUntil) : false;

        return $time !== false && $time - time() <= 30 * DAY_IN_SECONDS;
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
            // The key the person clicked on. Two keys can cover the same add-on, so the slug alone is ambiguous.
            $licenseKey = null;
            $maskedKey  = (string) $request->get_param('masked_key');
            if ($maskedKey !== '') {
                foreach (array_keys(LicenseHelper::getLicenses('all')) as $storedKey) {
                    if (str_repeat('•', max(0, strlen($storedKey) - 4)) . substr($storedKey, -4) === $maskedKey) {
                        $licenseKey = $storedKey;
                        break;
                    }
                }
            }

            if (empty($licenseKey)) {
                $licenseKey = LicenseHelper::getPluginLicense($addonSlug);
            }

            if (empty($licenseKey)) {
                return self::response(__('No license found for this add-on.', 'wp-sms'), 404);
            }

            $force   = (bool) $request->get_param('force');
            $license = LicenseHelper::getLicenseInfo($licenseKey);
            $expired = ($license['status'] ?? '') === 'license_expired';

            // Free this site's seat first, so the key can be used on another site. An expired key
            // has no seat to free, and "remove anyway" skips the server when it cannot be reached.
            if (!$expired && !$force) {
                try {
                    // Name an add-on that this key really covers.
                    $coveredSlug = !empty($license['products'][0]) ? $license['products'][0] : $addonSlug;
                    (new ApiCommunicator())->deactivateLicense($licenseKey, $coveredSlug);
                } catch (\Exception $e) {
                    return self::response(
                        sprintf(
                            /* translators: %s: error message from the license server */
                            __('Could not free this site on the license server: %s', 'wp-sms'),
                            $e->getMessage()
                        ),
                        502
                    );
                }
            }

            LicenseHelper::removeLicense($licenseKey);

            return self::response(
                $expired || $force
                    ? __('License removed from this site.', 'wp-sms')
                    : __('License removed. This site no longer uses a seat, so you can use the key on another site.', 'wp-sms'),
                200
            );
        } catch (\Exception $e) {
            return self::response($e->getMessage(), 400);
        }
    }
}

new AddonsApi();
