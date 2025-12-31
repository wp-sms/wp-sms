<?php

namespace WP_SMS\Admin\Settings;

use WP_SMS\Components\Singleton;
use WP_SMS\Option;
use WP_SMS\Gateway;
use WP_SMS\Newsletter;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * New Settings Page - React-based settings interface
 */
class NewSettingsPage extends Singleton
{
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
     * Initialize the settings page
     */
    public function init()
    {
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
        $list['new-settings'] = [
            'sub'      => 'wp-sms',
            'title'    => __('Settings (New)', 'wp-sms'),
            'name'     => __('Settings (New)', 'wp-sms'),
            'cap'      => 'wpsms_setting',
            'page_url' => 'new-settings',
            'callback' => __CLASS__,
            'priority' => 9,
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
     * Render the settings page
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
     * Enqueue assets for the settings page
     *
     * @param string $hook
     */
    public function enqueueAssets($hook)
    {
        if (strpos($hook, 'new-settings') === false) {
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
        wp_localize_script('wpsms-new-settings', 'wpSmsSettings', $this->getLocalizedData());
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
                    'wpsms-new-settings' . ($index > 0 ? '-' . $index : ''),
                    $distUrl . $cssFile,
                    [],
                    WP_SMS_VERSION
                );
            }
        }

        // Enqueue JS
        wp_enqueue_script(
            'wpsms-new-settings',
            $distUrl . $mainEntry['file'],
            [],
            WP_SMS_VERSION,
            true
        );

        // Add type="module" to the script tag for ESM
        add_filter('script_loader_tag', function ($tag, $handle) {
            if ($handle === 'wpsms-new-settings') {
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
        wp_register_script('wpsms-new-settings', '', [], null, true);
        wp_enqueue_script('wpsms-new-settings');
    }

    /**
     * Mask sensitive fields in settings array
     *
     * Replaces actual values with masked placeholder to prevent
     * sensitive data from being exposed in the frontend
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
                // Mark as having a value but don't expose the actual value
                $settings[$field] = '••••••••';
            }
        }

        return $settings;
    }

    /**
     * Get localized data for the React app
     *
     * @return array
     */
    private function getLocalizedData()
    {
        return [
            'apiUrl'      => rest_url('wpsms/v1/'),
            'nonce'       => wp_create_nonce('wp_rest'),
            'settings'    => $this->maskSensitiveSettings(Option::getOptions()),
            'proSettings' => $this->maskSensitiveSettings(Option::getOptions(true)),
            'addons'      => $this->getActiveAddons(),
            'gateways'    => Gateway::gateway(),
            'adminUrl'    => admin_url(),
            'siteUrl'     => site_url(),
            'version'     => WP_SMS_VERSION,
            'i18n'        => $this->getTranslations(),
            // Dynamic data for multi-select fields
            'countries'   => $this->getCountries(),
            'postTypes'   => $this->getPostTypes(),
            'taxonomies'  => $this->getTaxonomiesWithTerms(),
            'roles'       => $this->getUserRoles(),
            'groups'      => $this->getNewsletterGroups(),
        ];
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
                'number'     => 100, // Limit for performance
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
        $roles = wp_roles()->get_names();
        return $roles;
    }

    /**
     * Get newsletter subscriber groups
     *
     * @return array
     */
    private function getNewsletterGroups()
    {
        if (class_exists('WP_SMS\\Newsletter')) {
            $groups = Newsletter::getGroups();
            if (is_array($groups)) {
                $result = [];
                foreach ($groups as $group) {
                    if (isset($group->ID) && isset($group->name)) {
                        $result[$group->ID] = $group->name;
                    }
                }
                return $result;
            }
        }
        return [];
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
     * Get translations for the React app
     *
     * @return array
     */
    private function getTranslations()
    {
        return [
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
        ];
    }
}
