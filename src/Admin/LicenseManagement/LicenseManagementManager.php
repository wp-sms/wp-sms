<?php

namespace WP_SMS\Admin\LicenseManagement;

use Exception;
use WP_SMS;
use WP_SMS\Admin\LicenseManagement\Plugin\PluginActions;
use WP_SMS\Admin\LicenseManagement\Plugin\PluginHandler;
use WP_SMS\Admin\LicenseManagement\Plugin\PluginUpdater;
use WP_SMS\Components\Assets;

class LicenseManagementManager
{
    private $pluginHandler;
    private $handledPlugins = [];

    /**
     * Admin Page Slug
     *
     * @var string
     */
    public static $admin_menu_slug = 'wpsms_[slug]_page';

    public function __construct()
    {
        $this->pluginHandler = new PluginHandler();

        // Initialize the necessary components.
        $this->initActionCallbacks();

        add_action('init', [$this, 'initPluginUpdaters']);
        add_action('admin_init', [$this, 'showPluginActivationNotice']);
        add_filter('wp_sms_enable_upgrade_to_bundle', [$this, 'showUpgradeToBundle']);
        add_filter('wp_sms_admin_menu_list', [$this, 'addMenuItem']);
        add_action('admin_init', [$this, 'initAdminPreview']);

    }

    public function initAdminPreview()
    {
        // todo change the page address
        if (isset($_GET['page']) && $_GET['page'] == 'wp-sms-add-ons-1' && isset($_GET['tab']) && $_GET['tab'] == 'add-license') {
            add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
        }
    }

    public function enqueueScripts()
    {
        // todo change it to minified version
        $localization = [
            'ajax_url'       => admin_url('admin-ajax.php'),
            'rest_api_nonce' => wp_create_nonce('wp_rest'),
            'global'         => self::licenseHelperObject()
        ];
        Assets::script('license-manager', 'src/scripts/license.js', ['jquery', 'wp-sms-global-script'], $localization, true);
    }

    public function addMenuItem($items)
    {
        $items['plugins'] = [
            'sub'      => 'wp-sms',
            'title'    => __('Add-Ons', 'wp-sms'),
            'name'     => '<span class="wpsms-text-warning">' . __('Add-Ons', 'wp-sms') . '</span>',
            'page_url' => 'add-ons-1',
            'callback' => LicenseManagerPage::class,
            'cap'      => WP_SMS\User\UserHelper::validateCapability(WP_SMS\Utils\OptionUtil::get('manage_capability', 'manage_options')),
            'priority' => 90,
            'break'    => true,
        ];

        return $items;
    }

    /**
     * Initialize AJAX callbacks for various license management actions.
     */
    private function initActionCallbacks()
    {
        add_action('init', [new PluginActions(), 'registerAjaxCallbacks']);
    }

    /**
     * Initialize the PluginUpdater for all stored licenses.
     */
    public function initPluginUpdaters()
    {
        $storedLicenses = LicenseHelper::getLicenses();

        if (!empty($storedLicenses)) {
            foreach ($storedLicenses as $licenseKey => $licenseData) {
                foreach ($licenseData['products'] as $productSlug) {
                    // Avoid duplicate handling for the same product
                    if (!in_array($productSlug, $this->handledPlugins)) {
                        $this->initPluginUpdaterIfValid($productSlug, $licenseKey);
                    }
                }
            }
        }
    }

    /**
     * Convert Page Slug to Page key
     *
     * @param $page_slug
     * @return mixed
     * @example wps_hists_pages -> hits
     */
    public static function getPageKeyFromSlug($page_slug)
    {
        $admin_menu_slug = explode("[slug]", self::$admin_menu_slug);
        preg_match('/(?<=' . $admin_menu_slug[0] . ').*?(?=' . $admin_menu_slug[1] . ')/', $page_slug, $page_name);
        return $page_name; # for get use $page_name[0]
    }

    /**
     * Initialize PluginUpdater for a specific product and license key.
     *
     */
    private function licenseHelperObject()
    {
        $list = array();

        if (isset($_GET)) {
            foreach ($_GET as $key => $value) {
                if ($key == "page") {
                    $slug  = self::getPageKeyFromSlug(esc_html($value));
                    $value = $slug[0] ?? '';
                }
                if (!is_array($value)) {
                    $list['request_params'][esc_html($key)] = esc_html($value);
                } else {
                    // Ensure each value in the array is escaped properly
                    $value = array_map('esc_html', $value);
                    // Assign the entire escaped array to the request_params array
                    $list['request_params'][esc_html($key)] = $value;
                }
            }
        }

        $list['i18n'] = array(
            'more_detail'                  => __('View Details', 'wp-statistics'),
            'reload'                       => __('Reload', 'wp-statistics'),
            'online_users'                 => __('Online Visitors', 'wp-statistics'),
            'Realtime'                     => __('Realtime', 'wp-statistics'),
            'visitors'                     => __('Visitors', 'wp-statistics'),
            'visits'                       => __('Views', 'wp-statistics'),
            'today'                        => __('Today', 'wp-statistics'),
            'yesterday'                    => __('Yesterday', 'wp-statistics'),
            'week'                         => __('Last 7 days', 'wp-statistics'),
            'this-week'                    => __('This week', 'wp-statistics'),
            'last-week'                    => __('Last week', 'wp-statistics'),
            'month'                        => __('Last 30 days', 'wp-statistics'),
            'this-month'                   => __('This month', 'wp-statistics'),
            'last-month'                   => __('Last month', 'wp-statistics'),
            '7days'                        => __('Last 7 days', 'wp-statistics'),
            '30days'                       => __('Last 30 days', 'wp-statistics'),
            '60days'                       => __('Last 60 days', 'wp-statistics'),
            '90days'                       => __('Last 90 days', 'wp-statistics'),
            '6months'                      => __('Last 6 months', 'wp-statistics'),
            'year'                         => __('Last 12 months', 'wp-statistics'),
            'this-year'                    => __('This year (Jan-Today)', 'wp-statistics'),
            'last-year'                    => __('Last year', 'wp-statistics'),
            'total'                        => __('Total', 'wp-statistics'),
            'daily_total'                  => __('Daily Total', 'wp-statistics'),
            'date'                         => __('Date', 'wp-statistics'),
            'time'                         => __('Time', 'wp-statistics'),
            'browsers'                     => __('Browsers', 'wp-statistics'),
            'rank'                         => __('#', 'wp-statistics'),
            'flag'                         => __('Country Flag', 'wp-statistics'),
            'country'                      => __('Country', 'wp-statistics'),
            'visitor_count'                => __('Visitors', 'wp-statistics'),
            'id'                           => __('ID', 'wp-statistics'),
            'title'                        => __('Page', 'wp-statistics'),
            'link'                         => __('Page Link', 'wp-statistics'),
            'address'                      => __('Domain Address', 'wp-statistics'),
            'word'                         => __('Search Term', 'wp-statistics'),
            'browser'                      => __('Visitor\'s Browser', 'wp-statistics'),
            'city'                         => __('Visitor\'s City', 'wp-statistics'),
            'ip_hash'                      => __('IP Address/Hash', 'wp-statistics'),
            'ip_hash_placeholder'          => __('Enter IP (e.g., 192.168.1.1) or hash (#...)', 'wp-statistics'),
            'referring_site'               => __('Referring Site', 'wp-statistics'),
            'hits'                         => __('Views', 'wp-statistics'),
            'agent'                        => __('User Agent', 'wp-statistics'),
            'platform'                     => __('Operating System', 'wp-statistics'),
            'version'                      => __('Browser/OS Version', 'wp-statistics'),
            'page'                         => __('Visited Page', 'wp-statistics'),
            'str_today'                    => __('Today', 'wp-statistics'),
            'str_yesterday'                => __('Yesterday', 'wp-statistics'),
            'str_this_week'                => __('This Week', 'wp-statistics'),
            'str_last_week'                => __('Last Week', 'wp-statistics'),
            'str_this_month'               => __('This Month', 'wp-statistics'),
            'str_last_month'               => __('Last Month', 'wp-statistics'),
            'str_7days'                    => __('Last 7 days', 'wp-statistics'),
            'str_30days'                   => __('Last 30 days', 'wp-statistics'),
            'str_90days'                   => __('Last 90 days', 'wp-statistics'),
            'str_6months'                  => __('Last 6 months', 'wp-statistics'),
            'str_year'                     => __('This year', 'wp-statistics'),
            'str_this_year'                => __('This year', 'wp-statistics'),
            'str_last_year'                => __('Last year', 'wp-statistics'),
            'str_back'                     => __('Go Back', 'wp-statistics'),
            'str_custom'                   => __('Select Custom Range...', 'wp-statistics'),
            'str_more'                     => __('Additional Date Ranges', 'wp-statistics'),
            'custom'                       => __('Custom Date Range', 'wp-statistics'),
            'to'                           => __('To (End Date)', 'wp-statistics'),
            'from'                         => __('From (Start Date)', 'wp-statistics'),
            'go'                           => __('Apply Range', 'wp-statistics'),
            'no_data'                      => __('Sorry, there\'s no data available for this selection.', 'wp-statistics'),
            'count'                        => __('Total Number', 'wp-statistics'),
            'percentage'                   => __('Percent Share', 'wp-statistics'),
            'version_list'                 => __('Version', 'wp-statistics'),
            'filter'                       => __('Apply Filters', 'wp-statistics'),
            'filters'                      => __('Filters', 'wp-statistics'),
            'all'                          => __('All', 'wp-statistics'),
            'er_datepicker'                => __('Select Desired Time Range', 'wp-statistics'),
            'er_valid_ip'                  => __('Please enter a valid IP (e.g., 192.168.1.1) or hash (starting with #)', 'wp-statistics'),
            'please_wait'                  => __('Loading, Please Wait...', 'wp-statistics'),
            'user'                         => __('User', 'wp-statistics'),
            'rest_connect'                 => __('Failed to retrieve data. Please check the browser console and the XHR request under Network → XHR for details.', 'wp-statistics'),
            'privacy_compliant'            => __('Your WP Statistics settings are privacy-compliant.', 'wp-statistics'),
            'non_privacy_compliant'        => __('Your WP Statistics settings are not privacy-compliant. Please update your settings.', 'wp-statistics'),
            'no_result'                    => __('No recent data available.', 'wp-statistics'),
            'published'                    => __('Published', 'wp-statistics'),
            'author'                       => __('Author', 'wp-statistics'),
            'view_detailed_analytics'      => __('View Detailed Analytics', 'wp-statistics'),
            'enable_now'                   => __('Enable Now', 'wp-statistics'),
            'receive_weekly_email_reports' => __('Receive Weekly Email Reports', 'wp-statistics'),
            'close'                        => __('Close', 'wp-statistics'),
            'previous_period'              => __('Previous period', 'wp-statistics'),
            'view_content'                 => __('View Content', 'wp-statistics'),
            'downloading'                  => __('Downloading', 'wp-statistics'),
            'activated'                    => __('Activated', 'wp-statistics'),
            'active'                       => __('Active', 'wp-statistics'),
            'activating'                   => __('Activating ', 'wp-statistics'),
            'already_installed'            => __('Already installed', 'wp-statistics'),
            'failed'                       => __('Failed', 'wp-statistics'),
            'retry'                        => __('Retry', 'wp-statistics'),
            'redirecting'                  => __('Redirecting... Please wait', 'wp-statistics'),
            'last_view'                    => __('Last View', 'wp-statistics'),
            'visitor_info'                 => __('Visitor Info', 'wp-statistics'),
            'location'                     => __('Location', 'wp-statistics'),
            'name'                         => __('Name', 'wp-statistics'),
            'email'                        => __('Email', 'wp-statistics'),
            'role'                         => __('Role', 'wp-statistics'),
            'latest_page'                  => __('Latest Page', 'wp-statistics'),
            'referrer'                     => __('Referrer', 'wp-statistics'),
            'online_for'                   => __('Online For', 'wp-statistics'),
            'views'                        => __('Views', 'wp-statistics'),
            'view'                         => __('View', 'wp-statistics'),
            'waiting'                      => __('Waiting', 'wp-statistics'),
            'apply'                        => __('Apply'),
            'reset'                        => __('Reset'),
            'loading'                      => __('Loading'),
            'go_to_overview'               => __('Go to Overview'),
            'continue_to_next_step'        => __('Continue to Next Step', 'wp-statistics'),
            'action_required'              => __('Action Required', 'wp-statistics'),
        );

        return $list;
    }

    /**
     * Initialize PluginUpdater for a specific product and license key.
     *
     * @param string $pluginSlug The slug of the plugin (e.g., 'wp-sms-data-plus').
     * @param string $licenseKey The license key for the product.
     */
    private function initPluginUpdaterIfValid($pluginSlug, $licenseKey)
    {
        try {
            if (!$this->pluginHandler->isPluginActive($pluginSlug)) {
                return;
            }

            // Get the dynamic version of the plugin
            $pluginData = $this->pluginHandler->getPluginData($pluginSlug);
            if (!$pluginData) {
                throw new Exception(sprintf(__('Plugin data not found for: %s', 'wp-sms'), $pluginSlug));
            }

            // Initialize PluginUpdater with the version and license key
            $pluginUpdater = new PluginUpdater($pluginSlug, $pluginData['Version'], $licenseKey);
            $pluginUpdater->handle();

            $this->handledPlugins[] = $pluginSlug;

        } catch (Exception $e) {
            //todo
            WP_SMS::log(sprintf('Failed to initialize PluginUpdater for %s: %s', $pluginSlug, $e->getMessage()));
        }
    }

    /**
     * Loop through plugins and show license notice for those without a valid license
     */
    public function showPluginActivationNotice()
    {
        $plugins = $this->pluginHandler->getInstalledPlugins();

        foreach ($plugins as $plugin) {
            if (!LicenseHelper::isPluginLicenseValid($plugin['TextDomain'])) {
                $pluginUpdater = new PluginUpdater($plugin['TextDomain'], $plugin['Version']);
                $pluginUpdater->handleLicenseNotice();
            }
        }
    }

    /**
     * Show the "Upgrade To Premium" only if the user has a premium license.
     *
     * @return bool
     */
    public function showUpgradeToBundle()
    {
        return !LicenseHelper::isPremiumLicenseAvailable();
    }
}
