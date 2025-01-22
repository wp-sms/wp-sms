<?php

namespace WP_SMS\Admin\LicenseManagement\Plugin;

use Exception;
use stdClass;
use WP_SMS\Utils\MenuUtil;
use WP_SMS\Admin\LicenseManagement\ApiCommunicator;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Class PluginUpdater
 *
 * Handles updating WP SMS add-ons by fetching the latest version information from a remote API
 * and integrating it with the WordPress plugin update system.
 */
class PluginUpdater
{
    private $pluginSlug;
    private $pluginVersion;
    private $licenseKey;
    private $pluginFilePath;

    /**
     * PluginUpdater constructor.
     *
     * @param string $pluginSlug
     * @param string $pluginVersion
     * @param string $licenseKey
     */
    public function __construct($pluginSlug, $pluginVersion, $licenseKey = '')
    {
        $this->pluginSlug     = $pluginSlug;
        $this->pluginVersion  = $pluginVersion;
        $this->licenseKey     = $licenseKey;
        $this->pluginFilePath = $this->pluginSlug . '/' . $this->pluginSlug . '.php';
    }

    /**
     * Hooks to check for updates and add necessary filters and actions.
     */
    public function handle()
    {
        add_filter('plugins_api', [$this, 'pluginsApiInfo'], 20, 3);
        add_filter('pre_set_site_transient_update_plugins', [$this, 'checkForUpdate']);
        add_action('upgrader_process_complete', [$this, 'clearCache'], 10, 2);
    }

    public function handleLicenseNotice()
    {
        add_action("after_plugin_row_{$this->pluginFilePath}", [$this, 'showLicenseNotice'], 10, 2);
    }

    /**
     * Handle the plugins_api call.
     *
     * @param mixed $res
     * @param string $action
     * @param object $args
     * @return mixed
     */
    public function pluginsApiInfo($res, $action, $args)
    {
        if ($action !== 'plugin_information' || $this->pluginSlug !== $args->slug) {
            return $res;
        }

        $remote = $this->requestUpdateInfo();

        if (!$remote) {
            return $res;
        }

        $res = $this->formatPluginInfoResponse($remote);

        return $res;
    }

    /**
     * Fetch version info from the API.
     *
     * @return object|false
     */
    private function requestUpdateInfo()
    {
        if (empty($this->licenseKey)) {
            delete_site_transient('update_plugins'); // Clear update cache
            return false;
        }

        try {
            $apiCommunicator = new ApiCommunicator();
            $remote          = $apiCommunicator->getDownloadUrl($this->licenseKey, $this->pluginSlug);

            if (!$remote || (isset($remote->code) && isset($remote->message))) {
                throw new Exception($remote->message ?? 'Failed to retrieve remote plugin information.', $remote->code ?? 0);
            }

            if (isset($remote->tested)) {
                $remote->tested = $this->adjustPatchVersion($remote->tested);
            }

            return $remote;

        } catch (Exception $e) {
            WP_SMS::log($e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Check for updates by comparing versions.
     *
     * @param object $transient
     * @return object
     */
    public function checkForUpdate($transient)
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        $remote = $this->requestUpdateInfo();

        if ($remote) {
            $res = $this->formatUpdateResponse($remote);

            if (version_compare($this->pluginVersion, $remote->version, '<')) {
                $transient->response[$res->plugin] = $res;
            } else {
                $transient->no_update[$res->plugin] = $res;
            }
        }

        return $transient;
    }

    /**
     * Show a license notice if needed.
     *
     * @param string $pluginFile
     * @param array $pluginData
     */
    public function showLicenseNotice($pluginFile, $pluginData)
    {
        if (!$this->requestUpdateInfo()) {
            $screen  = get_current_screen();
            $columns = get_column_headers($screen);
            $colspan = is_countable($columns) ? count($columns) : 3;

            $isActive = is_plugin_active($this->pluginFilePath);

            ?>
            <tr class='license-error-tr plugin-update-tr update <?php echo $isActive ? 'active' : ''; ?>' data-plugin='<?php echo esc_attr($this->pluginFilePath); ?>'>
                <td colspan='<?php echo esc_attr($colspan); ?>' class='plugin-update'>
                    <div class='notice inline notice-warning notice-alt'>
                        <p>
                            <?php echo sprintf(__('Automatic updates are disabled for the <b>%s</b>.', 'wp-sms'), esc_html($pluginData['Name'])); ?>
                            <?php
                            $licensePageUrl = MenuUtil::getAdminUrl('plugins', ['tab' => 'add-license']); // Updated to use MenuUtil
                            echo sprintf(__('To unlock automatic updates, please <a href="%s">activate your license</a>.', 'wp-sms'), esc_url($licensePageUrl));
                            ?>
                        </p>
                    </div>
                </td>
            </tr>
            <?php
        }
    }

    /**
     * Clear cache after upgrades.
     *
     * @param \WP_Upgrader $upgrader
     * @param array $options
     */
    public function clearCache($upgrader, $options)
    {
        if ($options['action'] === 'update' && $options['type'] === 'plugin') {
            delete_site_transient('update_plugins');
        }
    }

    /**
     * Adjusts the patch version of the plugin to match the WordPress version.
     *
     * @param string $testedVersion
     * @return string
     */
    private function adjustPatchVersion($testedVersion)
    {
        global $wp_version;

        $testedParts = explode('.', $testedVersion);
        $wpParts     = explode('.', $wp_version);

        if (count($testedParts) >= 2 && count($wpParts) >= 2) {
            return $testedParts[0] . '.' . $testedParts[1] . '.' . ($wpParts[2] ?? '0');
        }

        return $testedVersion;
    }

    /**
     * Format the plugin information response.
     *
     * @param object $remote
     * @return stdClass
     */
    private function formatPluginInfoResponse($remote)
    {
        $res                 = new stdClass();
        $res->name           = $remote->name;
        $res->slug           = $remote->slug;
        $res->version        = $remote->version;
        $res->tested         = $remote->tested;
        $res->requires       = $remote->requires;
        $res->author         = $remote->author;
        $res->author_profile = $remote->author_profile;
        $res->download_link  = $remote->download_url;
        $res->requires_php   = $remote->requires_php;
        $res->last_updated   = $remote->last_updated;

        $res->sections = [
            'description'  => $remote->sections->description,
            'installation' => $remote->sections->installation,
            'changelog'    => $remote->sections->changelog,
        ];

        $res->icons = [
            '1x' => $remote->icons->low,
            '2x' => $remote->icons->high,
        ];

        $res->banners = [
            'low'  => $remote->banners->low,
            'high' => $remote->banners->high,
        ];

        return $res;
    }

    /**
     * Format the update response.
     *
     * @param object $remote
     * @return stdClass
     */
    private function formatUpdateResponse($remote)
    {
        $res              = new stdClass();
        $res->slug        = $this->pluginSlug;
        $res->plugin      = $this->pluginFilePath;
        $res->new_version = $remote->version;
        $res->tested      = $remote->tested;
        $res->package     = $remote->download_url;
        $res->icons       = [
            '1x' => $remote->icons->low,
            '2x' => $remote->icons->high,
        ];
        $res->banners     = [
            'low'  => $remote->banners->low,
            'high' => $remote->banners->high,
        ];

        return $res;
    }
}
