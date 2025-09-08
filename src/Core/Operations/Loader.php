<?php

namespace WP_SMS\Core\Operations;

use WP_SMS\Core\AbstractCore;
use WP_SMS\Utils\DBUtil as DB;
use WP_SMS\Utils\OptionUtil as Option;
use WP_SMS\Services\Database\Managers\TableHandler;

/**
 * Handles runtime registrations and the admin upgrade UI.
 *
 * Runs on load to register multisite create/drop handlers, add plugin row
 * meta links, and initialize the page-type updater, which displays an admin
 * notice and processes updates via AJAX until all records are typed.
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
    }

    public function addTableOnCreateBlog($blogId)
    {
        if (!is_plugin_active_for_network(plugin_basename(WP_SMS_MAIN_FILE))) {
            return;
        }

        $options = get_option(Option::$optName);
        switch_to_blog($blogId);
        TableHandler::createAllTables();
        update_option(Option::$optName, $options);
        restore_current_blog();
    }

    public function removeTableOnDeleteBlog($tables)
    {
        $tables[] = array_merge($tables, DB::table('all'));
        return $tables;
    }

    public function addMetaLinks($links, $file)
    {
        if ($file !== plugin_basename(WP_SMS_MAIN_FILE)) {
            return $links;
        }

        $pluginUrl = 'https://wordpress.org/plugins/wp-sms/';
        $links[]   = '<a href="' . esc_url($pluginUrl) . '" target="_blank" title="' . esc_attr__('Click here to visit the plugin on WordPress.org', 'wp-sms') . '">' . esc_html__('Visit WordPress.org page', 'wp-sms') . '</a>';
        $rateUrl   = 'https://wordpress.org/support/plugin/wp-sms/reviews/?rate=5#new-post';
        $links[]   = '<a href="' . esc_url($rateUrl) . '" target="_blank" title="' . esc_attr__('Click here to rate and review this plugin on WordPress.org', 'wp-sms') . '">' . esc_html__('Rate this plugin', 'wp-sms') . '</a>';

        return $links;
    }
}