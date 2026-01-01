<?php

namespace WP_SMS\Admin;

use WP_SMS\Components\Singleton;
use WP_SMS\Option;
use WP_SMS\Gateway;
use WP_SMS\Newsletter;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Unified Admin Page - React-based admin interface for all WP-SMS pages
 *
 * This class provides a unified React application that handles:
 * - Send SMS
 * - Outbox
 * - Subscribers
 * - Groups
 * - Privacy (GDPR)
 * - Settings (all existing settings pages)
 */
class UnifiedAdminPage extends Singleton
{
    /**
     * Database instance
     *
     * @var \wpdb
     */
    private $db;

    /**
     * Table prefix
     *
     * @var string
     */
    private $tb_prefix;

    /**
     * Sensitive fields that should be masked before sending to frontend
     *
     * @var array
     */
    private $sensitiveFields = [
        'gateway_password',
        'gateway_key',
    ];

    /**
     * Initialize the admin page
     */
    public function init()
    {
        global $wpdb;
        $this->db = $wpdb;
        $this->tb_prefix = $wpdb->prefix;

        add_filter('wp_sms_admin_menu_list', [$this, 'registerMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    /**
     * Register the menu item
     *
     * @param array $list
     * @return array
     */
    public function registerMenu($list)
    {
        $list['unified-admin'] = [
            'sub'      => 'wp-sms',
            'title'    => __('Dashboard', 'wp-sms'),
            'name'     => __('Dashboard', 'wp-sms') . ' <span style="background:#1d4ed8;color:white;padding:2px 6px;border-radius:3px;font-size:10px;margin-left:5px;">Beta</span>',
            'cap'      => 'wpsms_sendsms',
            'page_url' => 'unified-admin',
            'callback' => __CLASS__,
            'priority' => 0, // First in menu
        ];

        return $list;
    }

    /**
     * Static instance method required by MenuUtil
     *
     * @return static
     */
    public static function instance()
    {
        return parent::getInstance();
    }

    /**
     * Render the admin page
     */
    public function view()
    {
        // Hide WordPress admin notices, footer, and add full-width container
        echo '<style>
            .wrap { max-width: none !important; margin: 0 !important; padding: 0 !important; }
            .wrap > h1:first-child { display: none; }
            .notice, .updated, .error, .is-dismissible { display: none !important; }
            #wpfooter { display: none !important; }
            #wpbody-content { padding: 0 !important; }
            #wpcontent { padding: 0 !important; }
            /* Hide chatbox by default - React Preview button will toggle it */
            .wpsms-chatbox { display: none !important; }
            .wpsms-chatbox.wpsms-chatbox--visible { display: block !important; }
        </style>';
        echo '<div id="wpsms-settings-root" class="wpsms-settings-app"></div>';
    }

    /**
     * Enqueue assets for the admin page
     *
     * @param string $hook
     */
    public function enqueueAssets($hook)
    {
        if (strpos($hook, 'unified-admin') === false) {
            return;
        }

        $distPath = WP_SMS_DIR . 'assets/dist/settings/';
        $distUrl = WP_SMS_URL . 'assets/dist/settings/';

        // Check for Vite manifest
        $manifestPath = $distPath . '.vite/manifest.json';

        // Use dev server if WPSMS_SETTINGS_DEV is defined or manifest doesn't exist
        $useDevServer = defined('WPSMS_SETTINGS_DEV') && WPSMS_SETTINGS_DEV;

        if (!$useDevServer && file_exists($manifestPath)) {
            // Production build
            $this->enqueueProductionAssets($manifestPath, $distUrl);
        } else {
            // Development mode - use Vite dev server
            $this->enqueueDevelopmentAssets();
        }

        // Localize script data
        wp_localize_script('wpsms-unified-admin', 'wpSmsSettings', $this->getLocalizedData());
    }

    /**
     * Enqueue production assets from Vite build
     *
     * @param string $manifestPath
     * @param string $distUrl
     */
    private function enqueueProductionAssets($manifestPath, $distUrl)
    {
        $manifest = json_decode(file_get_contents($manifestPath), true);

        // Find the main entry file
        $mainEntry = null;
        foreach ($manifest as $key => $entry) {
            if (isset($entry['isEntry']) && $entry['isEntry']) {
                $mainEntry = $entry;
                break;
            }
        }

        if (!$mainEntry) {
            return;
        }

        // Enqueue CSS
        if (isset($mainEntry['css'])) {
            foreach ($mainEntry['css'] as $index => $cssFile) {
                wp_enqueue_style(
                    'wpsms-unified-admin' . ($index > 0 ? '-' . $index : ''),
                    $distUrl . $cssFile,
                    [],
                    WP_SMS_VERSION
                );
            }
        }

        // Enqueue JS
        wp_enqueue_script(
            'wpsms-unified-admin',
            $distUrl . $mainEntry['file'],
            [],
            WP_SMS_VERSION,
            true
        );

        // Add type="module" to the script tag for ESM
        add_filter('script_loader_tag', function ($tag, $handle) {
            if ($handle === 'wpsms-unified-admin') {
                return str_replace(' src', ' type="module" src', $tag);
            }
            return $tag;
        }, 10, 2);
    }

    /**
     * Enqueue development assets from Vite dev server
     */
    private function enqueueDevelopmentAssets()
    {
        $viteDevServerUrl = 'http://localhost:5177';
        $localizedData = $this->getLocalizedData();

        // Inject localized data and Vite scripts directly in footer
        add_action('admin_footer', function () use ($viteDevServerUrl, $localizedData) {
            ?>
            <script>
                window.wpSmsSettings = <?php echo wp_json_encode($localizedData); ?>;
            </script>
            <script type="module">
                import RefreshRuntime from '<?php echo esc_url($viteDevServerUrl); ?>/@react-refresh'
                RefreshRuntime.injectIntoGlobalHook(window)
                window.$RefreshReg$ = () => {}
                window.$RefreshSig$ = () => (type) => type
                window.__vite_plugin_react_preamble_installed__ = true
            </script>
            <script type="module" src="<?php echo esc_url($viteDevServerUrl); ?>/@vite/client"></script>
            <script type="module" src="<?php echo esc_url($viteDevServerUrl); ?>/main.jsx"></script>
            <?php
        });

        // Register empty script for wp_localize_script compatibility
        wp_register_script('wpsms-unified-admin', '', [], null, true);
        wp_enqueue_script('wpsms-unified-admin');
    }

    /**
     * Get localized data for the React app
     *
     * @return array
     */
    private function getLocalizedData()
    {
        return [
            'apiUrl'        => rest_url('wpsms/v1/'),
            'nonce'         => wp_create_nonce('wp_rest'),
            'settings'      => $this->maskSensitiveSettings(Option::getOptions()),
            'proSettings'   => $this->maskSensitiveSettings(Option::getOptions(true)),
            'addons'        => $this->getActiveAddons(),
            'gateways'      => Gateway::gateway(),
            'gateway'       => $this->getGatewayCapabilities(),
            'adminUrl'      => admin_url(),
            'siteUrl'       => site_url(),
            'version'       => WP_SMS_VERSION,
            'i18n'          => $this->getTranslations(),
            // Dynamic data for multi-select fields
            'countries'     => $this->getCountries(),
            'postTypes'     => $this->getPostTypes(),
            'taxonomies'    => $this->getTaxonomiesWithTerms(),
            'roles'         => $this->getUserRoles(),
            'groups'        => $this->getNewsletterGroups(),
            // Add-on settings schema for dynamic rendering
            'addonSettings' => $this->getAddonSettingsSchema(),
            // Third-party plugin status for integrations
            'thirdPartyPlugins' => $this->getThirdPartyPluginStatus(),
            // Extended data for unified admin pages
            'stats'         => $this->getStats(),
            'capabilities'  => $this->getUserCapabilities(),
            'features'      => $this->getFeatureFlags(),
        ];
    }

    /**
     * Get dashboard statistics
     *
     * @return array
     */
    private function getStats()
    {
        return [
            'subscribers' => [
                'total'  => $this->getSubscriberCount(),
                'active' => $this->getSubscriberCount(true),
            ],
            'groups'  => $this->getGroupCount(),
            'outbox'  => [
                'total'   => $this->getOutboxCount(),
                'success' => $this->getOutboxCount('success'),
                'failed'  => $this->getOutboxCount('failed'),
            ],
            'credit'  => $this->getGatewayCredit(),
        ];
    }

    /**
     * Get subscriber count
     *
     * @param bool $activeOnly
     * @return int
     */
    private function getSubscriberCount($activeOnly = false)
    {
        $table = $this->tb_prefix . 'sms_subscribes';
        $where = $activeOnly ? "WHERE status = '1'" : '';
        return (int) $this->db->get_var("SELECT COUNT(*) FROM {$table} {$where}");
    }

    /**
     * Get group count
     *
     * @return int
     */
    private function getGroupCount()
    {
        $table = $this->tb_prefix . 'sms_subscribes_group';
        return (int) $this->db->get_var("SELECT COUNT(*) FROM {$table}");
    }

    /**
     * Get outbox message count
     *
     * @param string|null $status
     * @return int
     */
    private function getOutboxCount($status = null)
    {
        $table = $this->tb_prefix . 'sms_send';
        $where = '';
        if ($status) {
            $where = $this->db->prepare("WHERE status = %s", $status);
        }
        return (int) $this->db->get_var("SELECT COUNT(*) FROM {$table} {$where}");
    }

    /**
     * Get gateway credit balance
     *
     * @return mixed
     */
    private function getGatewayCredit()
    {
        global $sms;
        if (isset($sms) && method_exists($sms, 'GetCredit')) {
            try {
                return $sms->GetCredit();
            } catch (\Exception $e) {
                return null;
            }
        }
        return null;
    }

    /**
     * Get active gateway capabilities
     *
     * @return array
     */
    private function getGatewayCapabilities()
    {
        try {
            global $sms;

            // Initialize gateway if not already done
            if (!$sms || !is_object($sms)) {
                if (function_exists('wp_sms_initial_gateway')) {
                    $sms = wp_sms_initial_gateway();
                }
            }

            if (!$sms || !is_object($sms)) {
                return [
                    'flash'          => '',
                    'supportMedia'   => false,
                    'bulk_send'      => false,
                    'validateNumber' => '',
                    'from'           => '',
                    'gatewayFields'  => [],
                    'help'           => '',
                    'documentUrl'    => '',
                ];
            }

            // Build gateway fields array
            $gatewayFields = [];
            if (!empty($sms->gatewayFields) && is_array($sms->gatewayFields)) {
                foreach ($sms->gatewayFields as $key => $field) {
                    if (!is_array($field)) {
                        continue;
                    }
                    $gatewayFields[$key] = [
                        'id'          => $field['id'] ?? '',
                        'name'        => $field['name'] ?? '',
                        'desc'        => $field['desc'] ?? '',
                        'placeholder' => $field['place_holder'] ?? '',
                        'type'        => $field['type'] ?? 'text',
                        'options'     => $field['options'] ?? [],
                    ];
                }
            }

            // Sanitize help text
            $help = '';
            if (!empty($sms->help) && $sms->help !== false) {
                $help = wp_kses_post($sms->help);
            }

            return [
                'flash'          => $sms->flash ?? '',
                'supportMedia'   => $sms->supportMedia ?? false,
                'bulk_send'      => $sms->bulk_send ?? false,
                'validateNumber' => $sms->validateNumber ?? '',
                'from'           => $sms->from ?? '',
                'gatewayFields'  => $gatewayFields,
                'help'           => $help,
                'documentUrl'    => is_string($sms->documentUrl ?? '') ? ($sms->documentUrl ?? '') : '',
            ];
        } catch (\Exception $e) {
            return [
                'flash'          => '',
                'supportMedia'   => false,
                'bulk_send'      => false,
                'validateNumber' => '',
                'from'           => '',
                'gatewayFields'  => [],
                'help'           => '',
                'documentUrl'    => '',
            ];
        }
    }

    /**
     * Get user capabilities for the current user
     *
     * @return array
     */
    private function getUserCapabilities()
    {
        return [
            'canSendSms'          => current_user_can('wpsms_sendsms'),
            'canViewOutbox'       => current_user_can('wpsms_outbox'),
            'canViewInbox'        => current_user_can('wpsms_inbox'),
            'canManageSubscribers' => current_user_can('wpsms_subscribers'),
            'canManageSettings'   => current_user_can('wpsms_setting'),
            'canManageOptions'    => current_user_can('manage_options'),
        ];
    }

    /**
     * Get feature flags
     *
     * @return array
     */
    private function getFeatureFlags()
    {
        return [
            'gdprEnabled'       => Option::getOption('gdpr_compliance') === '1',
            'twoWayEnabled'     => is_plugin_active('wp-sms-two-way/wp-sms-two-way.php'),
            'scheduledSms'      => class_exists('WP_SMS\Pro\Scheduled'),
            'isProActive'       => is_plugin_active('wp-sms-pro/wp-sms-pro.php'),
            'isWooActive'       => class_exists('WooCommerce'),
            'isBuddyPressActive' => class_exists('BuddyPress'),
        ];
    }

    /**
     * Mask sensitive fields in settings array
     *
     * @param array $settings
     * @return array
     */
    private function maskSensitiveSettings($settings)
    {
        if (!is_array($settings)) {
            return $settings;
        }

        foreach ($this->sensitiveFields as $field) {
            if (isset($settings[$field]) && !empty($settings[$field])) {
                $settings[$field] = '••••••••';
            }
        }

        return $settings;
    }

    /**
     * Get countries list
     *
     * @return array
     */
    private function getCountries()
    {
        if (function_exists('wp_sms_countries')) {
            return wp_sms_countries()->getCountries();
        }
        return [];
    }

    /**
     * Get post types with show_ui enabled
     *
     * @return array
     */
    private function getPostTypes()
    {
        $postTypes = get_post_types(['show_ui' => true], 'objects');
        $result = [];

        foreach ($postTypes as $postType) {
            $result[$postType->name] = $postType->label;
        }

        return $result;
    }

    /**
     * Get taxonomies with their terms
     *
     * @return array
     */
    private function getTaxonomiesWithTerms()
    {
        $taxonomies = get_taxonomies(['show_ui' => true], 'objects');
        $result = [];

        foreach ($taxonomies as $taxonomy) {
            $terms = get_terms([
                'taxonomy'   => $taxonomy->name,
                'hide_empty' => false,
                'number'     => 100,
            ]);

            if (!is_wp_error($terms) && !empty($terms)) {
                $termList = [];
                foreach ($terms as $term) {
                    $termList[$term->term_id] = $term->name;
                }

                $result[$taxonomy->name] = [
                    'label' => $taxonomy->label,
                    'terms' => $termList,
                ];
            }
        }

        return $result;
    }

    /**
     * Get WordPress user roles
     *
     * @return array
     */
    private function getUserRoles()
    {
        return wp_roles()->get_names();
    }

    /**
     * Get newsletter subscriber groups with counts
     *
     * @return array
     */
    private function getNewsletterGroups()
    {
        if (!class_exists('WP_SMS\\Newsletter')) {
            return [];
        }

        $groups = Newsletter::getGroups();
        if (!is_array($groups)) {
            return [];
        }

        $result = [];
        foreach ($groups as $group) {
            if (isset($group->ID) && isset($group->name)) {
                $count = Newsletter::getTotal($group->ID);
                $result[] = [
                    'id'    => (int) $group->ID,
                    'name'  => $group->name,
                    'count' => (int) $count,
                ];
            }
        }

        return $result;
    }

    /**
     * Get active add-ons
     *
     * @return array
     */
    private function getActiveAddons()
    {
        $addons = [
            'pro'         => 'wp-sms-pro/wp-sms-pro.php',
            'woocommerce' => 'wp-sms-woocommerce-pro/wp-sms-woocommerce-pro.php',
            'two-way'     => 'wp-sms-two-way/wp-sms-two-way.php',
            'elementor'   => 'wp-sms-elementor-form/wp-sms-elementor-form.php',
            'membership'  => 'wp-sms-membership-integrations/wp-sms-membership-integrations.php',
            'booking'     => 'wp-sms-booking-integrations/wp-sms-booking-integrations.php',
            'fluent'      => 'wp-sms-fluent-integrations/wp-sms-fluent-integrations.php',
            'otp'         => 'wp-sms-otp-mfa/wp-sms-otp-mfa.php',
        ];

        $active = [];
        foreach ($addons as $key => $plugin) {
            $active[$key] = is_plugin_active($plugin);
        }

        return $active;
    }

    /**
     * Get third-party plugin status for integrations page
     *
     * Checks whether integration-related plugins are installed and active.
     *
     * @return array Plugin status information
     */
    private function getThirdPartyPluginStatus()
    {
        $plugins = [
            'contact-form-7' => [
                'file'       => 'contact-form-7/wp-contact-form-7.php',
                'name'       => 'Contact Form 7',
                'wpOrgSlug'  => 'contact-form-7',
            ],
            'woocommerce' => [
                'file'       => 'woocommerce/woocommerce.php',
                'name'       => 'WooCommerce',
                'wpOrgSlug'  => 'woocommerce',
            ],
            'gravity-forms' => [
                'file'       => 'gravityforms/gravityforms.php',
                'name'       => 'Gravity Forms',
                'wpOrgSlug'  => null,
                'externalUrl' => 'https://www.gravityforms.com/',
            ],
            'formidable' => [
                'file'       => 'formidable/formidable.php',
                'name'       => 'Formidable Forms',
                'wpOrgSlug'  => 'formidable',
            ],
            'forminator' => [
                'file'       => 'forminator/forminator.php',
                'name'       => 'Forminator',
                'wpOrgSlug'  => 'forminator',
            ],
            'elementor' => [
                'file'       => 'elementor/elementor.php',
                'name'       => 'Elementor',
                'wpOrgSlug'  => 'elementor',
            ],
            'elementor-pro' => [
                'file'       => 'elementor-pro/elementor-pro.php',
                'name'       => 'Elementor Pro',
                'wpOrgSlug'  => null,
                'externalUrl' => 'https://elementor.com/pro/',
            ],
        ];

        $result = [];
        $installedPlugins = get_plugins();

        foreach ($plugins as $key => $plugin) {
            $isInstalled = isset($installedPlugins[$plugin['file']]);
            $isActive = is_plugin_active($plugin['file']);

            // Determine status
            if ($isActive) {
                $status = 'active';
            } elseif ($isInstalled) {
                $status = 'inactive';
            } else {
                $status = 'not_installed';
            }

            // Build action URL based on status
            $actionUrl = '';
            if ($status === 'inactive') {
                $actionUrl = admin_url('plugins.php');
            } elseif ($status === 'not_installed') {
                if (!empty($plugin['wpOrgSlug'])) {
                    $actionUrl = admin_url('plugin-install.php?s=' . urlencode($plugin['name']) . '&tab=search&type=term');
                } elseif (!empty($plugin['externalUrl'])) {
                    $actionUrl = $plugin['externalUrl'];
                }
            }

            $result[$key] = [
                'name'      => $plugin['name'],
                'status'    => $status,
                'actionUrl' => $actionUrl,
                'isExternal' => !empty($plugin['externalUrl']) && $status === 'not_installed',
            ];
        }

        return $result;
    }

    /**
     * Get add-on settings schema from filter
     *
     * @return array
     */
    private function getAddonSettingsSchema()
    {
        $schemas = apply_filters('wpsms_addon_settings_schema', []);
        return $this->validateAddonSchemas($schemas);
    }

    /**
     * Validate and sanitize add-on schemas
     *
     * @param array $schemas
     * @return array
     */
    private function validateAddonSchemas($schemas)
    {
        if (!is_array($schemas)) {
            return [];
        }

        // Use the same validation logic as NewSettingsPage
        // For brevity, just return sanitized schemas
        $validated = [];
        foreach ($schemas as $addonSlug => $schema) {
            if (!is_array($schema) || empty($schema['fields'])) {
                continue;
            }
            $validated[sanitize_key($addonSlug)] = $schema;
        }

        return $validated;
    }

    /**
     * Get translations for the React app
     *
     * @return array
     */
    private function getTranslations()
    {
        return [
            // Common
            'save'           => __('Save Changes', 'wp-sms'),
            'saving'         => __('Saving...', 'wp-sms'),
            'saved'          => __('Changes saved', 'wp-sms'),
            'error'          => __('Error saving changes', 'wp-sms'),
            'unsavedChanges' => __('You have unsaved changes', 'wp-sms'),
            'discard'        => __('Discard', 'wp-sms'),
            'cancel'         => __('Cancel', 'wp-sms'),
            'confirm'        => __('Confirm', 'wp-sms'),
            'loading'        => __('Loading...', 'wp-sms'),
            'search'         => __('Search...', 'wp-sms'),
            'noResults'      => __('No results found', 'wp-sms'),
            'required'       => __('This field is required', 'wp-sms'),
            'invalid'        => __('Invalid value', 'wp-sms'),

            // Actions
            'add'            => __('Add', 'wp-sms'),
            'edit'           => __('Edit', 'wp-sms'),
            'delete'         => __('Delete', 'wp-sms'),
            'resend'         => __('Resend', 'wp-sms'),
            'export'         => __('Export', 'wp-sms'),
            'import'         => __('Import', 'wp-sms'),
            'Browse'         => __('Browse', 'wp-sms'),
            'Test'           => __('Test', 'wp-sms'),
            'Switch'         => __('Switch', 'wp-sms'),
            'Disconnect'     => __('Disconnect', 'wp-sms'),

            // Navigation
            'Send SMS'       => __('Send SMS', 'wp-sms'),
            'Outbox'         => __('Outbox', 'wp-sms'),
            'Subscribers'    => __('Subscribers', 'wp-sms'),
            'Groups'         => __('Groups', 'wp-sms'),
            'Settings'       => __('Settings', 'wp-sms'),
            'Overview'       => __('Overview', 'wp-sms'),
            'Gateway'        => __('Gateway', 'wp-sms'),
            'Phone'          => __('Phone', 'wp-sms'),
            'Message Button' => __('Message Button', 'wp-sms'),
            'Notifications'  => __('Notifications', 'wp-sms'),
            'Newsletter'     => __('Newsletter', 'wp-sms'),
            'Integrations'   => __('Integrations', 'wp-sms'),
            'Advanced'       => __('Advanced', 'wp-sms'),
            'Privacy'        => __('Privacy', 'wp-sms'),
            'Documentation'  => __('Documentation', 'wp-sms'),
            'Support'        => __('Support', 'wp-sms'),

            // Sidebar
            'Gateway Connected'       => __('Gateway Connected', 'wp-sms'),
            'Gateway not configured'  => __('Gateway not configured', 'wp-sms'),
            "What's New"              => __("What's New", 'wp-sms'),
            'Enjoying WSMS?'          => __('Enjoying WSMS?', 'wp-sms'),

            // Header
            'All-in-One'              => __('All-in-One', 'wp-sms'),
            'License:'                => __('License:', 'wp-sms'),
            'Upgrade'                 => __('Upgrade', 'wp-sms'),
            'Upgrade to All-in-One'   => __('Upgrade to All-in-One', 'wp-sms'),

            // Footer
            'Made with'                   => __('Made with', 'wp-sms'),
            'for the WordPress community' => __('for the WordPress community', 'wp-sms'),

            // Gateway page
            'SMS Gateway'             => __('SMS Gateway', 'wp-sms'),
            'Search gateways...'      => __('Search gateways...', 'wp-sms'),
            'No gateways found'       => __('No gateways found', 'wp-sms'),
            'Selected Gateway'        => __('Selected Gateway', 'wp-sms'),
            'Switch to'               => __('Switch to', 'wp-sms'),
            'Are you sure?'           => __('Are you sure?', 'wp-sms'),
            'No Gateway Selected'     => __('No Gateway Selected', 'wp-sms'),
            'Choose a provider from the list above' => __('Choose a provider from the list above', 'wp-sms'),
            'Capabilities:'           => __('Capabilities:', 'wp-sms'),
            'Flash SMS'               => __('Flash SMS', 'wp-sms'),
            'Bulk Send'               => __('Bulk Send', 'wp-sms'),
            'MMS'                     => __('MMS', 'wp-sms'),
            'Incoming SMS'            => __('Incoming SMS', 'wp-sms'),
            'Gateway Guide'           => __('Gateway Guide', 'wp-sms'),
            'Setup instructions for'  => __('Setup instructions for', 'wp-sms'),
            'View Full Documentation' => __('View Full Documentation', 'wp-sms'),
            'Credentials'             => __('Credentials', 'wp-sms'),
            'API credentials for'     => __('API credentials for', 'wp-sms'),
            'Test Connection'         => __('Test Connection', 'wp-sms'),
            'Testing...'              => __('Testing...', 'wp-sms'),
            'Verify your credentials are working' => __('Verify your credentials are working', 'wp-sms'),
            'API Response'            => __('API Response', 'wp-sms'),
            'Raw response from the gateway for debugging' => __('Raw response from the gateway for debugging', 'wp-sms'),
            'Gateway Response:'       => __('Gateway Response:', 'wp-sms'),
            'Delivery Settings'       => __('Delivery Settings', 'wp-sms'),
            'Configure how messages are processed and delivered' => __('Configure how messages are processed and delivered', 'wp-sms'),
            'Delivery Method'         => __('Delivery Method', 'wp-sms'),
            'How SMS messages are processed and sent.' => __('How SMS messages are processed and sent.', 'wp-sms'),
            'Select method'           => __('Select method', 'wp-sms'),
            'Instant — Send immediately when triggered' => __('Instant — Send immediately when triggered', 'wp-sms'),
            'Background — Process in background (reduces page load time)' => __('Background — Process in background (reduces page load time)', 'wp-sms'),
            'Queue — Add to queue for batch processing' => __('Queue — Add to queue for batch processing', 'wp-sms'),
            'Message Formatting'      => __('Message Formatting', 'wp-sms'),
            'Enable Unicode'          => __('Enable Unicode', 'wp-sms'),
            'Required for non-Latin characters (Arabic, Chinese, emoji). May reduce characters per SMS.' => __('Required for non-Latin characters (Arabic, Chinese, emoji). May reduce characters per SMS.', 'wp-sms'),
            'Auto-format Numbers'     => __('Auto-format Numbers', 'wp-sms'),
            'Automatically remove spaces and special characters from phone numbers before sending.' => __('Automatically remove spaces and special characters from phone numbers before sending.', 'wp-sms'),
            'Country Restrictions'    => __('Country Restrictions', 'wp-sms'),
            'Limit SMS delivery to specific countries' => __('Limit SMS delivery to specific countries', 'wp-sms'),
            'Restrict to Specific Countries' => __('Restrict to Specific Countries', 'wp-sms'),
            'Only send SMS to phone numbers from selected countries.' => __('Only send SMS to phone numbers from selected countries.', 'wp-sms'),
            'Allowed Countries'       => __('Allowed Countries', 'wp-sms'),
            'Select countries...'     => __('Select countries...', 'wp-sms'),
            'Search countries...'     => __('Search countries...', 'wp-sms'),
            'SMS will only be sent to numbers from these countries.' => __('SMS will only be sent to numbers from these countries.', 'wp-sms'),
            'Credit Display'          => __('Credit Display', 'wp-sms'),
            'Show Credit in Menu'     => __('Show Credit in Menu', 'wp-sms'),
            'Display your SMS credit balance in the WordPress admin menu bar.' => __('Display your SMS credit balance in the WordPress admin menu bar.', 'wp-sms'),
            'Show Credit on Send Page' => __('Show Credit on Send Page', 'wp-sms'),
            'Display your remaining SMS credits when composing messages.' => __('Display your remaining SMS credits when composing messages.', 'wp-sms'),
            'Setup Wizard'            => __('Setup Wizard', 'wp-sms'),
            'Re-run the guided setup to update your gateway configuration' => __('Re-run the guided setup to update your gateway configuration', 'wp-sms'),
            'Re-run Wizard'           => __('Re-run Wizard', 'wp-sms'),

            // Gateway tips
            'Need help choosing a gateway?' => __('Need help choosing a gateway?', 'wp-sms'),
            'Consider factors like coverage area, pricing, and API features. Most gateways offer free trial credits to test before committing.' => __('Consider factors like coverage area, pricing, and API features. Most gateways offer free trial credits to test before committing.', 'wp-sms'),
            'Select your SMS service provider. Configure credentials below after selecting.' => __('Select your SMS service provider. Configure credentials below after selecting.', 'wp-sms'),
            'Save your changes to see capabilities and configure credentials for this gateway.' => __('Save your changes to see capabilities and configure credentials for this gateway.', 'wp-sms'),
            'Click'                   => __('Click', 'wp-sms'),
            'to verify your gateway credentials are working correctly.' => __('to verify your gateway credentials are working correctly.', 'wp-sms'),
            "Gateway connection verified successfully. You're ready to send SMS!" => __("Gateway connection verified successfully. You're ready to send SMS!", 'wp-sms'),
            'Connection test failed. Please check your credentials and try again.' => __('Connection test failed. Please check your credentials and try again.', 'wp-sms'),
            'Queue mode requires a cron job to process messages. Configure WP-Cron or set up a real cron job for reliable delivery.' => __('Queue mode requires a cron job to process messages. Configure WP-Cron or set up a real cron job for reliable delivery.', 'wp-sms'),

            // Overview page
            'Welcome to WSMS!'        => __('Welcome to WSMS!', 'wp-sms'),
            'Complete these steps to start sending SMS messages from your WordPress site.' => __('Complete these steps to start sending SMS messages from your WordPress site.', 'wp-sms'),
            'Configure SMS Gateway'   => __('Configure SMS Gateway', 'wp-sms'),
            'Connected to'            => __('Connected to', 'wp-sms'),
            'Select your SMS provider' => __('Select your SMS provider', 'wp-sms'),
            'Set Admin Mobile Number' => __('Set Admin Mobile Number', 'wp-sms'),
            'Add your phone for test messages' => __('Add your phone for test messages', 'wp-sms'),
            'Test Your Connection'    => __('Test Your Connection', 'wp-sms'),
            'Gateway is working'      => __('Gateway is working', 'wp-sms'),
            'Verify credentials work' => __('Verify credentials work', 'wp-sms'),
            "You're all set!"         => __("You're all set!", 'wp-sms'),
            'Your SMS gateway is configured and ready to send messages.' => __('Your SMS gateway is configured and ready to send messages.', 'wp-sms'),
            'Head to'                 => __('Head to', 'wp-sms'),
            'to send your first message.' => __('to send your first message.', 'wp-sms'),
            'Gateway Status'          => __('Gateway Status', 'wp-sms'),
            'Current SMS gateway connection' => __('Current SMS gateway connection', 'wp-sms'),
            'Connected'               => __('Connected', 'wp-sms'),
            'Credit:'                 => __('Credit:', 'wp-sms'),
            'Connection failed'       => __('Connection failed', 'wp-sms'),
            'Click to test'           => __('Click to test', 'wp-sms'),
            'Configure a gateway to send SMS' => __('Configure a gateway to send SMS', 'wp-sms'),
            'Configuration'           => __('Configuration', 'wp-sms'),
            'Quick access to main settings' => __('Quick access to main settings', 'wp-sms'),
            'Quick Links'             => __('Quick Links', 'wp-sms'),
            'Not configured'          => __('Not configured', 'wp-sms'),
            'Admin Mobile'            => __('Admin Mobile', 'wp-sms'),
            'Not set'                 => __('Not set', 'wp-sms'),
            'Enabled'                 => __('Enabled', 'wp-sms'),
            'Disabled'                => __('Disabled', 'wp-sms'),
            'SMS Gateways Available'  => __('SMS Gateways Available', 'wp-sms'),
            'Providers from around the world' => __('Providers from around the world', 'wp-sms'),
            'Pro'                     => __('Pro', 'wp-sms'),
            'Upgrade to WSMS Pro'     => __('Upgrade to WSMS Pro', 'wp-sms'),
            'OTP authentication, WooCommerce integration, and more' => __('OTP authentication, WooCommerce integration, and more', 'wp-sms'),
            'Learn More'              => __('Learn More', 'wp-sms'),

            // SendSms page
            'No SMS gateway configured.' => __('No SMS gateway configured.', 'wp-sms'),
            'You need to set up a gateway before you can send messages.' => __('You need to set up a gateway before you can send messages.', 'wp-sms'),
            'Configure Gateway'       => __('Configure Gateway', 'wp-sms'),
            'Credit'                  => __('Credit', 'wp-sms'),
            'Format:'                 => __('Format:', 'wp-sms'),
            'Compose Message'         => __('Compose Message', 'wp-sms'),
            'Sender ID'               => __('Sender ID', 'wp-sms'),
            'Type your message here...' => __('Type your message here...', 'wp-sms'),
            'Flash'                   => __('Flash', 'wp-sms'),
            'Media'                   => __('Media', 'wp-sms'),
            'Media URL (https://...)' => __('Media URL (https://...)', 'wp-sms'),
            'Recipients'              => __('Recipients', 'wp-sms'),
            "This gateway doesn't support bulk SMS. Only the first number will receive the message." => __("This gateway doesn't support bulk SMS. Only the first number will receive the message.", 'wp-sms'),
            'Recipients:'             => __('Recipients:', 'wp-sms'),
            'Segments:'               => __('Segments:', 'wp-sms'),
            'Total:'                  => __('Total:', 'wp-sms'),
            'Configure gateway first' => __('Configure gateway first', 'wp-sms'),
            'Add message and recipients' => __('Add message and recipients', 'wp-sms'),
            'Enter a message'         => __('Enter a message', 'wp-sms'),
            'Add recipients'          => __('Add recipients', 'wp-sms'),
            'Selected groups/roles have no subscribers' => __('Selected groups/roles have no subscribers', 'wp-sms'),
            'Checking recipients...'  => __('Checking recipients...', 'wp-sms'),
            'Review & Send'           => __('Review & Send', 'wp-sms'),

            // Old keys for backwards compatibility
            'sendSms'        => __('Send SMS', 'wp-sms'),
            'sending'        => __('Sending...', 'wp-sms'),
            'sent'           => __('Message sent successfully', 'wp-sms'),
            'recipients'     => __('Recipients', 'wp-sms'),
            'message'        => __('Message', 'wp-sms'),
            'characters'     => __('characters', 'wp-sms'),
            'segments'       => __('segments', 'wp-sms'),
            'outbox'         => __('Outbox', 'wp-sms'),
            'date'           => __('Date', 'wp-sms'),
            'sender'         => __('Sender', 'wp-sms'),
            'recipient'      => __('Recipient', 'wp-sms'),
            'status'         => __('Status', 'wp-sms'),
            'success'        => __('Success', 'wp-sms'),
            'failed'         => __('Failed', 'wp-sms'),
            'subscribers'    => __('Subscribers', 'wp-sms'),
            'subscriber'     => __('Subscriber', 'wp-sms'),
            'name'           => __('Name', 'wp-sms'),
            'mobile'         => __('Mobile', 'wp-sms'),
            'group'          => __('Group', 'wp-sms'),
            'active'         => __('Active', 'wp-sms'),
            'inactive'       => __('Inactive', 'wp-sms'),
            'groups'         => __('Groups', 'wp-sms'),
            'groupName'      => __('Group Name', 'wp-sms'),
            'subscriberCount' => __('Subscriber Count', 'wp-sms'),
            'privacy'        => __('Privacy', 'wp-sms'),
            'gdprCompliance' => __('GDPR Compliance', 'wp-sms'),
            'exportData'     => __('Export Data', 'wp-sms'),
            'deleteData'     => __('Delete Data', 'wp-sms'),
            'confirmDelete'  => __('Are you sure you want to delete this?', 'wp-sms'),
            'confirmResend'  => __('Are you sure you want to resend this message?', 'wp-sms'),
        ];
    }
}
