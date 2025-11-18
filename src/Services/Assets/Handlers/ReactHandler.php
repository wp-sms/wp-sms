<?php

namespace WP_SMS\Services\Assets\Handlers;

use WP_SMS\Services\Assets\BaseAssets;
use WP_SMS\Services\LocalizedData\LocalizedDataFactory;

/**
 * React Assets Service
 *
 * Handles WordPress admin React assets (CSS/JS) in WP SMS plugin.
 * Manages loading and enqueuing of React-specific styles and scripts.
 *
 * @package WP_SMS\Service\Assets
 * @since   7.2
 */
class ReactHandler extends BaseAssets
{
    /**
     * Manifest main JS file path
     *
     * @var string
     */
    private $manifestMainJs = '';

    /**
     * Manifest main CSS file paths
     *
     * @var array
     */
    private $manifestMainCss = [];

    /**
     * Initialize the React assets manager
     *
     * @return void
     */
    public function __construct()
    {
        $this->setContext('react');
        $this->setAssetDir('public/react');

        add_action('admin_enqueue_scripts', [$this, 'styles'], 10);
        add_action('admin_enqueue_scripts', [$this, 'scripts'], 10);
    }

    /**
     * Register and enqueue React admin styles
     *
     * @return void
     */
    public function styles()
    {
        // Get Current Screen ID
        $screenId = $this->getScreenId();

        if ('sms_page_wp-sms-new-settings' !== $screenId) {
            return;
        }

        $this->loadManifest();

        if (empty($this->manifestMainCss)) {
            return;
        }

        foreach ($this->manifestMainCss as $index => $cssFile) {
            wp_enqueue_style($this->getAssetHandle() . '-' . $index, $this->getUrl($cssFile), [], $this->getVersion());
        }
    }

    /**
     * Register and enqueue React admin scripts
     *
     * @param string $hook Current admin page hook
     * @return void
     */
    public function scripts($hook = '')
    {
        // Get Current Screen ID
        $screenId = $this->getScreenId();

        if ('sms_page_wp-sms-new-settings' !== $screenId) {
            return;
        }

        remove_all_actions('admin_notices');

        $this->loadManifest();

        if (empty($this->manifestMainJs)) {
            return;
        }

        wp_register_script(
            $this->getAssetHandle(),
            $this->getUrl($this->manifestMainJs),
            ['wp-i18n'],
            null,
            true
        );

        // Add type="module" attribute for ES module support.
        add_filter('script_loader_tag', [$this, 'addModuleAttribute'], 10, 2);

        wp_enqueue_script($this->getAssetHandle());

        $this->printLocalizedData($hook);
        $this->loadTranslations();
    }    

    /**
     * Print localized data for React
     *
     * Since wp_localize_script doesn't work with wp_enqueue_script_module,
     * we need to print the data directly to window object before the module loads.
     *
     * @param string $hook Current admin page hook
     *
     * @return void
     */
    public function printLocalizedData($hook)
    {
        $l10n = $this->getLocalizedData($hook);

        if (is_array($l10n)) {
            foreach ($l10n as $key => $value) {
                if (!is_scalar($value)) {
                    continue;
                }

                $l10n[$key] = html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8');
            }
        }

        $script = sprintf('window.WP_SMS_DATA = %s;', wp_json_encode($l10n));

        wp_print_inline_script_tag($script);
    }

    /**
     * Get localized data for React JavaScript
     *
     * @param string $hook Current admin page hook
     * @return array
     */
    protected function getLocalizedData($hook)
    {
        return LocalizedDataFactory::react()->getAllData();
    }

    /**
     * Add type="module" attribute to script tag
     *
     * @param string $tag    The script tag HTML.
     * @param string $handle The script handle.
     * @return string Modified script tag.
     */
    public function addModuleAttribute($tag, $handle)
    {
        if ($handle === $this->getAssetHandle()) {
            return str_replace(' src', ' type="module" src', $tag);
        }
        return $tag;
    }

    /**
     * Load translations from .mo file and set locale data for wp.i18n
     *
     * Since wp_set_script_translations requires JSON files, we load
     * translations directly from .mo files and inject them via inline script.
     *
     * @return void
     */
    private function loadTranslations()
    {
        $locale = determine_locale();

        if ('en_US' === $locale) {
            return;
        }

        $moFile = $this->findMoFile($locale);

        if (!$moFile || !file_exists($moFile)) {
            return;
        }

        $translations = $this->loadMoFile($moFile);

        if (empty($translations)) {
            return;
        }

        $jedData = [
            '' => [
                'domain' => 'wp-sms',
                'lang'   => $locale,
            ],
        ];

        foreach ($translations as $original => $translated) {
            if (empty($original)) {
                continue;
            }
            $jedData[$original] = [$translated];
        }

        $script = sprintf(
            'window.WP_SMS_TRANSLATIONS = %s;',
            wp_json_encode($jedData)
        );

        wp_add_inline_script($this->getAssetHandle(), $script, 'before');
    }

    /**
     * Find the .mo file for the given locale
     *
     * @param string $locale The locale to find
     * @return string|null The path to the .mo file or null if not found
     */
    private function findMoFile($locale)
    {
        // Check multiple locations where translations might be stored
        $locations = [
            // Plugin's own languages folder
            WP_SMS_DIR . "languages/wp-sms-{$locale}.mo",
            // WordPress global languages folder
            \WP_LANG_DIR . "/plugins/wp-sms-{$locale}.mo",
        ];

        foreach ($locations as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        // Check custom translation plugin directories (e.g., Loco Translate, WPML, etc.)
        $customPaths = glob(\WP_CONTENT_DIR . "/languages/*/plugins/wp-sms-{$locale}.mo");
        if (!empty($customPaths)) {
            return $customPaths[0];
        }

        return null;
    }

    /**
     * Load translations from a .mo file
     *
     * @param string $moFile Path to the .mo file
     * @return array Associative array of original => translated strings
     */
    private function loadMoFile($moFile)
    {
        if (!class_exists('MO')) {
            require_once \ABSPATH . \WPINC . '/pomo/mo.php';
        }

        $mo = new \MO();  // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

        if (!$mo->import_from_file($moFile)) {
            return [];
        }

        $translations = [];

        foreach ($mo->entries as $entry) {
            if (!empty($entry->singular) && !empty($entry->translations[0])) {
                $key = $entry->singular;

                // Handle context
                if (!empty($entry->context)) {
                    $key = $entry->context . "\x04" . $entry->singular;
                }

                $translations[$key] = $entry->translations[0];
            }
        }

        return $translations;
    }

    /**
     * Load Vite manifest file to get built asset paths.
     *
     * Reads the .vite/manifest.json file generated by Vite build process
     * to determine the correct paths for the main JS and CSS files.
     * This ensures proper asset loading for React components in production.
     *
     * @return void
     */
    private function loadManifest()
    {
        if (!empty($this->manifestMainJs) && !empty($this->manifestMainCss)) {
            return;
        }

        $manifestPath = $this->getUrl('.vite/manifest.json', true);

        if (empty($manifestPath) || !file_exists($manifestPath)) {
            return;
        }

        $manifestContent = file_get_contents($manifestPath);
        $decodedContent  = json_decode($manifestContent, true);

        if (empty($decodedContent['main.tsx'])) {
            return;
        }

        $this->manifestMainJs  = $decodedContent['main.tsx']['file'] ?? '';
        $this->manifestMainCss = $decodedContent['main.tsx']['css'] ?? [];
    }
}
