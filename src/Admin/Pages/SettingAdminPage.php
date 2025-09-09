<?php

namespace WP_SMS\Admin\Pages;

class SettingAdminPage
{
    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerSettingPage']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('admin_notices', [$this, 'removeOtherPluginNotices'], 0);
    }

    public function registerSettingPage(): void
    {
        add_submenu_page(
            'wp-sms',
            esc_html__('New Settings', 'wp-sms'),
            esc_html__('New Settings', 'wp-sms'),
            'wpsms_setting',
            'wp-sms-new-settings',
            [$this, 'renderSettings'],
            6
        );
    }

    /**
     * Remove admin notices from other plugins on WP-SMS settings page
     */
    public function removeOtherPluginNotices(): void
    {
        if (!isset($_GET['page']) || $_GET['page'] !== 'wp-sms-new-settings') {
            return;
        }

        // Remove all admin notices except WP-SMS ones
        remove_all_actions('admin_notices');
        remove_all_actions('all_admin_notices');

        // Re-add only WP-SMS notices if needed
        add_action('admin_notices', function () {
            // WP-SMS specific notices can be added here if needed
        });
    }

    public function enqueueAssets(): void
    {
        if (!isset($_GET['page']) || $_GET['page'] !== 'wp-sms-new-settings') {
            return;
        }

        // Enqueue WordPress media uploader
        wp_enqueue_media();

        // Enqueue WordPress React dependencies
        wp_enqueue_script('wp-i18n');

        // Load assets directly from build folder
        $this->enqueueBuildAssets();

        // Add import map and WP_SMS_DATA to page head
        add_action('admin_head', function () {
            // Get all registered assets
            $assets = isset($GLOBALS['wp_sms_assets']) ? $GLOBALS['wp_sms_assets'] : [];

            // Create import map for ES modules
            $import_map = $this->createImportMap();
?>
            <script type="importmap">
                <?php echo json_encode($import_map, JSON_PRETTY_PRINT); ?>
            </script>
            <script type="text/javascript">
                window.WP_SMS_DATA = <?php echo json_encode([
                                            'nonce'   => wp_create_nonce('wp_rest'),
                                            'restUrl' => esc_url_raw(rest_url('wpsms/v1/')),
                                            'frontend_build_url' => WP_SMS_FRONTEND_BUILD_URL,
                                            'assets' => $assets
                                        ]); ?>;
            </script>
<?php
        });

        // Also localize the script as backup
        $assets = isset($GLOBALS['wp_sms_assets']) ? $GLOBALS['wp_sms_assets'] : [];
        wp_localize_script(
            'wp-sms-settings',
            'WP_SMS_DATA',
            [
                'nonce'   => wp_create_nonce('wp_rest'),
                'restUrl' => esc_url_raw(rest_url('wpsms/v1/')),
                'frontend_build_url' => WP_SMS_FRONTEND_BUILD_URL,
                'assets' => $assets
            ]
        );
    }

    /**
     * Enqueue assets directly from build folder
     */
    private function enqueueBuildAssets(): void
    {
        $build_url = WP_SMS_FRONTEND_BUILD_URL;
        $build_dir = WP_SMS_DIR . 'frontend/build/assets/';

        // Load all CSS files
        $this->enqueueAllCssFiles($build_url, $build_dir);

        // Load all JS files (main bundle + dynamic chunks)
        $this->enqueueAllJsFiles($build_url, $build_dir);

        // Load other assets (fonts, images, etc.) - just register them for reference
        $this->registerOtherAssets($build_url, $build_dir);

        // Debug: Log available files in development
        $this->debugAvailableFiles($build_dir);
    }

    /**
     * Enqueue all CSS files from build directory
     */
    private function enqueueAllCssFiles(string $build_url, string $build_dir): void
    {
        if (!is_dir($build_dir)) {
            return;
        }

        $css_files = glob($build_dir . '*.css');

        foreach ($css_files as $css_file) {
            $filename = basename($css_file);
            $handle = 'wp-sms-' . pathinfo($filename, PATHINFO_FILENAME);
            $style_url = $build_url . 'assets/' . $filename;

            wp_enqueue_style($handle, $style_url, [], WP_SMS_VERSION);
        }
    }

    /**
     * Enqueue all JS files from build directory (simplified approach)
     */
    private function enqueueAllJsFiles(string $build_url, string $build_dir): void
    {
        if (!is_dir($build_dir)) {
            return;
        }

        $js_files = glob($build_dir . '*.js');

        // Sort files to ensure main loads first
        usort($js_files, function ($a, $b) {
            $a_name = basename($a);
            $b_name = basename($b);

            // Main file gets highest priority
            if (strpos($a_name, 'main-') === 0) return -1;
            if (strpos($b_name, 'main-') === 0) return 1;

            return 0;
        });

        foreach ($js_files as $js_file) {
            $filename = basename($js_file);
            $handle = 'wp-sms-' . pathinfo($filename, PATHINFO_FILENAME);
            $script_url = $build_url . 'assets/' . $filename;

            // Load as ES modules (add type="module" attribute)
            $deps = strpos($filename, 'main-') === 0 ? ['wp-i18n'] : [];
            wp_enqueue_script($handle, $script_url, $deps, WP_SMS_VERSION, true);
        }

        // Add type="module" attribute to all our scripts
        add_filter('script_loader_tag', [$this, 'addModuleTypeToScripts'], 10, 3);
    }

    /**
     * Add type="module" attribute to our script tags
     */
    public function addModuleTypeToScripts($tag, $handle, $src): string
    {
        // Only add type="module" to our WP-SMS scripts
        if (strpos($handle, 'wp-sms-') === 0) {
            return str_replace('<script ', '<script type="module" ', $tag);
        }
        return $tag;
    }

    /**
     * Register other assets (fonts, images, etc.) for reference
     */
    private function registerOtherAssets(string $build_url, string $build_dir): void
    {
        if (!is_dir($build_dir)) {
            return;
        }

        // Get all files that are not JS or CSS
        $all_files = glob($build_dir . '*');
        $other_files = array_filter($all_files, function ($file) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            return !in_array($ext, ['js', 'css']) && is_file($file);
        });

        foreach ($other_files as $file) {
            $filename = basename($file);
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $asset_url = $build_url . 'assets/' . $filename;

            // Store asset URLs in a global variable for JavaScript access
            if (!isset($GLOBALS['wp_sms_assets'])) {
                $GLOBALS['wp_sms_assets'] = [];
            }
            $GLOBALS['wp_sms_assets'][$filename] = $asset_url;

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("WP-SMS Registered asset: {$filename} ({$ext})");
            }
        }
    }

    /**
     * Find asset file by prefix and extension
     */
    private function findAssetFile(string $directory, string $prefix, string $extension): ?string
    {
        if (!is_dir($directory)) {
            return null;
        }

        $files = glob($directory . $prefix . '*' . $extension);
        return !empty($files) ? basename($files[0]) : null;
    }

    /**
     * Create import map for ES modules
     */
    private function createImportMap(): array
    {
        $build_url = WP_SMS_FRONTEND_BUILD_URL;
        $build_dir = WP_SMS_DIR . 'frontend/build/assets/';

        if (!is_dir($build_dir)) {
            return ['imports' => []];
        }

        $js_files = glob($build_dir . '*.js');
        $imports = [];

        // Add base URL for assets directory
        $assets_base_url = $build_url . 'assets/';

        foreach ($js_files as $js_file) {
            $filename = basename($js_file);
            $module_name = './' . $filename;
            $module_url = $assets_base_url . $filename;
            $imports[$module_name] = $module_url;
        }

        return ['imports' => $imports];
    }

    /**
     * Debug method to log all available files (can be called for troubleshooting)
     */
    private function debugAvailableFiles(string $build_dir): void
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $js_files = glob($build_dir . '*.js');
        $css_files = glob($build_dir . '*.css');
        $all_files = glob($build_dir . '*');
        $other_files = array_filter($all_files, function ($file) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            return !in_array($ext, ['js', 'css']) && is_file($file);
        });

        $js_filenames = array_map('basename', $js_files);
        $css_filenames = array_map('basename', $css_files);
        $other_filenames = array_map('basename', $other_files);

        error_log('WP-SMS Available JS files: ' . implode(', ', $js_filenames));
        error_log('WP-SMS Available CSS files: ' . implode(', ', $css_filenames));
        error_log('WP-SMS Available other assets: ' . implode(', ', $other_filenames));
    }

    public function renderSettings(): void
    {
        echo '<div class="wrap wp-sms-react-wrap">';
        echo '<div id="wp-sms-react-root"></div>';
        echo '</div>';
    }
}
