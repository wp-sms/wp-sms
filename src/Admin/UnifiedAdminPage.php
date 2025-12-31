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
            'name'     => __('Dashboard', 'wp-sms') . ' <span style="background:#3b82f6;color:white;padding:2px 6px;border-radius:3px;font-size:10px;margin-left:5px;">Beta</span>',
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
        $viteDevServerUrl = 'http://localhost:3000';
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
            // SMS
            'sendSms'        => __('Send SMS', 'wp-sms'),
            'sending'        => __('Sending...', 'wp-sms'),
            'sent'           => __('Message sent successfully', 'wp-sms'),
            'recipients'     => __('Recipients', 'wp-sms'),
            'message'        => __('Message', 'wp-sms'),
            'characters'     => __('characters', 'wp-sms'),
            'segments'       => __('segments', 'wp-sms'),
            // Outbox
            'outbox'         => __('Outbox', 'wp-sms'),
            'date'           => __('Date', 'wp-sms'),
            'sender'         => __('Sender', 'wp-sms'),
            'recipient'      => __('Recipient', 'wp-sms'),
            'status'         => __('Status', 'wp-sms'),
            'success'        => __('Success', 'wp-sms'),
            'failed'         => __('Failed', 'wp-sms'),
            // Subscribers
            'subscribers'    => __('Subscribers', 'wp-sms'),
            'subscriber'     => __('Subscriber', 'wp-sms'),
            'name'           => __('Name', 'wp-sms'),
            'mobile'         => __('Mobile', 'wp-sms'),
            'group'          => __('Group', 'wp-sms'),
            'active'         => __('Active', 'wp-sms'),
            'inactive'       => __('Inactive', 'wp-sms'),
            // Groups
            'groups'         => __('Groups', 'wp-sms'),
            'groupName'      => __('Group Name', 'wp-sms'),
            'subscriberCount' => __('Subscriber Count', 'wp-sms'),
            // Privacy
            'privacy'        => __('Privacy', 'wp-sms'),
            'gdprCompliance' => __('GDPR Compliance', 'wp-sms'),
            'exportData'     => __('Export Data', 'wp-sms'),
            'deleteData'     => __('Delete Data', 'wp-sms'),
            // Confirmations
            'confirmDelete'  => __('Are you sure you want to delete this?', 'wp-sms'),
            'confirmResend'  => __('Are you sure you want to resend this message?', 'wp-sms'),
        ];
    }
}
