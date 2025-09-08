<?php

namespace WP_SMS\Core\Operations;

use WP_SMS\Core\AbstractCore;
use WP_SMS\Utils\DBUtil as DB;
use WP_SMS\Utils\OptionUtil as Option;

/**
 * Handles uninstall-time cleanup.
 *
 * On uninstall (and per site on multisite), this class removes plugin data
 * when the "delete_data_on_uninstall" option is enabled: options, transients,
 * scheduled hooks, user/post meta, and plugin-created tables.
 *
 * @see register_uninstall_hook()
 * @package WP_SMS\Core\Operations
 */
class Uninstaller extends AbstractCore
{
    /**
     * Uninstaller constructor.
     *
     * @param bool $networkWide
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
        $this->loadRequiredFiles();


        if (is_multisite()) {
            $blog_ids = $this->wpdb->get_col("SELECT `blog_id` FROM {$this->wpdb->blogs}");

            foreach ($blog_ids as $blog_id) {
                switch_to_blog($blog_id);

                if (Option::get('delete_data_on_uninstall')) {
                    $this->cleanupSiteData();
                }

                restore_current_blog();
            }
        } else {
            if (Option::get('delete_data_on_uninstall')) {
                $this->cleanupSiteData();
            }
        }
    }

    /**
     * Removes database options, user meta keys & tables
     */
    public function cleanupSiteData()
    {}

    /**
     * Load core classes needed during uninstall.
     *
     * @return void
     * @todo Remove after PSR-4 autoloading is in place.
     */
    private function loadRequiredFiles()
    {}
}