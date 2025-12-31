<?php

namespace WP_SMS\Core\Operations;

use WP_SMS\Core\AbstractCore;
use WP_SMS\Services\Database\Managers\TableHandler;
use WP_SMS\Services\Database\Migrations\BackgroundProcess\BackgroundProcessManager;

/**
 * Handles runtime registrations and plugin row meta links.
 *
 * Runs on load to register multisite create/drop handlers and add plugin row meta links.
 *
 * @package WP_SMS\Core\Operations
 */
class Loader extends AbstractCore
{
    /**
     * Loader constructor.
     *
     * @return void
     */
    public function __construct($networkWide = false)
    {
        parent::__construct($networkWide);
        $this->execute();
    }

    /**
     * Execute the core function.
     *
     * @return void
     */
    public function execute()
    {
        add_action('wpmu_new_blog', [$this, 'addTableOnCreateBlog'], 10, 1);
        add_filter('wpmu_drop_tables', [$this, 'removeTableOnDeleteBlog']);
        add_filter('plugin_row_meta', [$this, 'addMetaLinks'], 10, 2);

        // Initialize background process manager
        new BackgroundProcessManager();
    }

    /**
     * Add tables when a new blog is created in multisite.
     *
     * @param int $blogId
     * @return void
     */
    public function addTableOnCreateBlog($blogId)
    {
        if (!is_plugin_active_for_network(plugin_basename(WP_SMS_DIR . 'wp-sms.php'))) {
            return;
        }

        $options = get_option('wpsms_settings');
        switch_to_blog($blogId);

        try {
            TableHandler::createAllTables();
        } catch (\Exception $e) {
            error_log('[WP SMS] Failed to create tables for new blog: ' . $e->getMessage());
        }

        update_option('wpsms_settings', $options);
        restore_current_blog();
    }

    /**
     * Remove tables when a blog is deleted in multisite.
     *
     * @param array $tables
     * @return array
     */
    public function removeTableOnDeleteBlog($tables)
    {
        global $wpdb;

        $pluginTables = [
            'sms_subscribes',
            'sms_subscribes_group',
            'sms_send',
            'sms_otp',
            'sms_otp_attempts',
        ];

        foreach ($pluginTables as $tbl) {
            $tables[] = $wpdb->prefix . $tbl;
        }

        return $tables;
    }

    /**
     * Add meta links to the plugin row.
     *
     * @param array $links
     * @param string $file
     * @return array
     */
    public function addMetaLinks($links, $file)
    {
        if ($file !== plugin_basename(WP_SMS_DIR . 'wp-sms.php')) {
            return $links;
        }

        $pluginUrl = 'https://wordpress.org/plugins/wp-sms/';
        $links[]   = '<a href="' . esc_url($pluginUrl) . '" target="_blank" title="' . esc_attr__('Click here to visit the plugin on WordPress.org', 'wp-sms') . '">' . esc_html__('Visit WordPress.org page', 'wp-sms') . '</a>';

        $rateUrl = 'https://wordpress.org/support/plugin/wp-sms/reviews/?rate=5#new-post';
        $links[] = '<a href="' . esc_url($rateUrl) . '" target="_blank" title="' . esc_attr__('Click here to rate and review this plugin on WordPress.org', 'wp-sms') . '">' . esc_html__('Rate this plugin', 'wp-sms') . '</a>';

        return $links;
    }
}
