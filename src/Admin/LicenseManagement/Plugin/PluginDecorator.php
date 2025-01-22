<?php
namespace WP_SMS\Admin\LicenseManagement\Plugin;
namespace WP_SMS\Admin\LicenseManagement\Plugin;

use Exception;
use WP_SMS\Admin\LicenseManagement\ApiCommunicator;
use WP_SMS\Admin\LicenseManagement\LicenseHelper;
use WP_SMS\Admin\LicenseManagement\Plugin\PluginHandler;
use WP_SMS\Utils\MenuUtil;

class PluginDecorator
{
    private $plugin;
    private $pluginHandler;
    private $apiCommunicator;

    public function __construct($plugin)
    {
        $this->apiCommunicator  = new ApiCommunicator();
        $this->pluginHandler    = new PluginHandler();
        $this->plugin           = $plugin;
    }

    public function getId()
    {
        return $this->plugin->id;
    }

    public function getSlug()
    {
        return $this->plugin->slug;
    }

    public function getName()
    {
        return $this->plugin->name;
    }

    public function getDescription()
    {
        return $this->plugin->description;
    }

    public function getShortDescription()
    {
        return $this->plugin->short_description;
    }

    public function getIcon()
    {
        $iconPath = "assets/images/add-ons/{$this->getSlug()}.svg";
        if (file_exists(WP_SMS_DIR . $iconPath)) {
            return esc_url(WP_SMS_URL . $iconPath);
        }

        return $this->getThumbnail();
    }

    public function getThumbnail()
    {
        return $this->plugin->thumbnail;
    }

    public function getPrice()
    {
        return $this->plugin->price;
    }

    public function getLabel()
    {
        return $this->plugin->label;
    }

    public function getLabelClass()
    {
        if (stripos($this->getLabel(), 'new') !== false) {
            return 'new';
        }

        if (stripos($this->getLabel(), 'updated') !== false) {
            return 'updated';
        }

        return 'updated';
    }

    public function getVersion()
    {
        return $this->plugin->version;
    }

    public function getChangelogUrl()
    {
        return $this->plugin->changelog_url;
    }

    public function getChangelog()
    {
        return $this->plugin->changelog;
    }

    public function getProductUrl()
    {
        return $this->plugin->product_url;
    }

    public function getDocumentationUrl()
    {
        return $this->plugin->documentation_url;
    }

    public function isLicenseValid()
    {
        return LicenseHelper::isPluginLicenseValid($this->getSlug());
    }

    public function isLicenseExpired()
    {
        return LicenseHelper::isPluginLicenseExpired($this->getSlug());
    }

    public function getStatus()
    {
        if (!$this->isInstalled()) {
            return 'not_installed';
        }

        if ($this->isLicenseExpired()) {
            return 'license_expired';
        }

        if (!$this->isLicenseValid()) {
            return 'not_licensed';
        }

        if (!$this->isActivated()) {
            return 'not_activated';
        }

        return 'activated';
    }

    public function getStatusLabel()
    {
        switch ($this->getStatus()) {
            case 'not_installed':
                return __('Not Installed', 'wp-sms');
            case 'not_licensed':
                return __('Needs License', 'wp-sms');
            case 'license_expired':
                return __('License Expired', 'wp-sms');
            case 'not_activated':
                return __('Inactive', 'wp-sms');
            case 'activated':
                return __('Activated', 'wp-sms');
            default:
                return __('Unknown', 'wp-sms');
        }
    }

    public function getStatusClass()
    {
        switch ($this->getStatus()) {
            case 'not_installed':
                return 'disable';
            case 'not_activated':
                return 'primary';
            case 'not_licensed':
            case 'license_expired':
                return 'danger';
            case 'activated':
                return 'success';
            default:
                throw new Exception('Unknown status');
        }
    }

    public function isInstalled()
    {
        return $this->pluginHandler->isPluginInstalled($this->getSlug());
    }

    public function isActivated()
    {
        return $this->pluginHandler->isPluginActive($this->getSlug());
    }

    public function getDownloadUrl()
    {
        $downloadUrl = $this->apiCommunicator->getDownloadUrlFromLicense($this->getLicenseKey(), $this->getSlug());
        return $downloadUrl ?? null;
    }

    public function getLicenseKey()
    {
        return LicenseHelper::getPluginLicense($this->getSlug());
    }

    /**
     * Returns add-on's settings page link.
     *
     * @return string Settings URL.
     */
    public function getSettingsUrl()
    {
        $pluginName = str_replace('wp-sms-', '', $this->getSlug());
        $tab        = !empty($pluginName) ? "$pluginName-settings" : '';

        return MenuUtil::getAdminUrl('settings', ['tab' => $tab]); // Updated to use MenuUtil
    }

    public function isUpdateAvailable()
    {
        if (!$this->isInstalled()) {
            return null;
        }

        $installedPlugin = null;
        try {
            $installedPlugin = $this->pluginHandler->getPluginData($this->getSlug());

            if (empty($installedPlugin) || empty($installedPlugin['Version'])) {
                return true;
            }
        } catch (\Exception $e) {
            return null;
        }

        return version_compare($this->getVersion(), $installedPlugin['Version'], '>');
    }
}
