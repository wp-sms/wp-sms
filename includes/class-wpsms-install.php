<?php

namespace WP_SMS;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

class Install
{

    public function __construct()
    {
        add_action('wpmu_new_blog', array($this, 'add_table_on_create_blog'), 10, 1);
        add_filter('wpmu_drop_tables', array($this, 'remove_table_on_delete_blog'));
    }

    /**
     * Adding new MYSQL Table in Activation Plugin
     *
     * @param Not param
     */
    public static function create_table($network_wide)
    {
        global $wpdb;

        if (is_multisite() && $network_wide) {
            $blog_ids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
            foreach ($blog_ids as $blog_id) {
                switch_to_blog($blog_id);

                self::table_sql();

                restore_current_blog();
            }
        } else {
            self::table_sql();
        }

    }

    /**
     * Table SQL
     *
     * @param Not param
     */
    public static function table_sql()
    {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $charset_collate = $wpdb->get_charset_collate();

        $table_name = $wpdb->prefix . 'sms_subscribes';
        if ($wpdb->get_var("show tables like '{$table_name}'") != $table_name) {
            $create_sms_subscribes = ("CREATE TABLE IF NOT EXISTS {$table_name}(
            ID int(10) NOT NULL auto_increment,
            date DATETIME,
            name VARCHAR(250),
            mobile VARCHAR(20) NOT NULL,
            status tinyint(1),
            activate_key INT(11),
            group_ID int(5),
            PRIMARY KEY(ID)) $charset_collate;");

            dbDelta($create_sms_subscribes);
        }

        $table_name = $wpdb->prefix . 'sms_subscribes_group';
        if ($wpdb->get_var("show tables like '{$table_name}'") != $table_name) {
            $create_sms_subscribes_group = ("CREATE TABLE IF NOT EXISTS {$table_name}(
            ID int(10) NOT NULL auto_increment,
            name VARCHAR(250),
            PRIMARY KEY(ID)) $charset_collate");

            dbDelta($create_sms_subscribes_group);
        }

        $table_name = $wpdb->prefix . 'sms_send';
        if ($wpdb->get_var("show tables like '{$table_name}'") != $table_name) {
            $create_sms_send = ("CREATE TABLE IF NOT EXISTS {$table_name}(
            ID int(10) NOT NULL auto_increment,
            date DATETIME,
            sender VARCHAR(20) NOT NULL,
            message TEXT NOT NULL,
            recipient TEXT NOT NULL,
  			response TEXT NOT NULL,
  			status varchar(10) NOT NULL,
            PRIMARY KEY(ID)) $charset_collate");

            dbDelta($create_sms_send);
        }
    }

    /**
     * Creating plugin tables
     *
     * @param $network_wide
     */
    static function install($network_wide)
    {
        global $wp_sms_db_version;

        self::create_table($network_wide);

        add_option('wp_sms_db_version', WP_SMS_VERSION);

        // Delete notification new wp_version option
        delete_option('wp_notification_new_wp_version');

        if (is_admin()) {
            self::upgrade();
        }
    }

    /**
     * Upgrade plugin requirements if needed
     */
    static function upgrade()
    {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        global $wpdb;
        $charset_collate       = $wpdb->get_charset_collate();
        $installer_wpsms_ver   = get_option('wp_sms_db_version');
        $outboxTable           = $wpdb->prefix . 'sms_send';
        $subscribersTable      = $wpdb->prefix . 'sms_subscribes';
        $subscribersGroupTable = $wpdb->prefix . 'sms_subscribes_group';

        if ($installer_wpsms_ver < WP_SMS_VERSION) {

            // Add response and status for outbox
            $column = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s ",
                DB_NAME, $outboxTable, 'response'
            ));

            if (empty($column)) {
                $wpdb->query("ALTER TABLE {$outboxTable} ADD status varchar(10) NOT NULL AFTER recipient, ADD response TEXT NOT NULL AFTER recipient");
            }

            // Fix columns length issue
            $wpdb->query("ALTER TABLE {$subscribersTable} MODIFY name VARCHAR(250)");

            // Delete old last credit option
            delete_option('wp_last_credit');

            // Change charset sms_send table to utf8mb4 if not
            $result = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s ",
                DB_NAME, $outboxTable, 'message'
            ));

            if ($result->COLLATION_NAME != $wpdb->collate) {
                $wpdb->query("ALTER TABLE {$outboxTable} CONVERT TO CHARACTER SET {$wpdb->charset} COLLATE {$wpdb->collate}");
            }

            // Change charset sms_subscribes table to utf8mb4 if not
            $result = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s ",
                DB_NAME, $subscribersTable, 'name'
            ));

            if ($result->COLLATION_NAME != $wpdb->collate) {
                $wpdb->query("ALTER TABLE {$subscribersTable} CONVERT TO CHARACTER SET {$wpdb->charset} COLLATE {$wpdb->collate}");
            }

            // Change charset sms_subscribes_group table to utf8mb4 if not
            $result = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s ",
                DB_NAME, $subscribersGroupTable, 'name'
            ));

            if ($result->COLLATION_NAME != $wpdb->collate) {
                $wpdb->query("ALTER TABLE {$subscribersGroupTable} CONVERT TO CHARACTER SET {$wpdb->charset} COLLATE {$wpdb->collate}");
            }

            update_option('wp_sms_db_version', WP_SMS_VERSION);
        }

        /**
         * Add media column in send table
         */
        if (!$wpdb->get_var("SHOW COLUMNS FROM `{$outboxTable}` like 'media'")) {
            $wpdb->query("ALTER TABLE `{$outboxTable}` ADD `media` TEXT NULL AFTER `recipient`");
        }
    }

    /**
     * Creating Table for New Blog in wordpress
     *
     * @param $blog_id
     */
    public function add_table_on_create_blog($blog_id)
    {
        if (is_plugin_active_for_network('wp-sms/wp-sms.php')) {
            switch_to_blog($blog_id);

            self::table_sql();

            restore_current_blog();
        }
    }

    /**
     * Remove Table On Delete Blog Wordpress
     *
     * @param $tables
     *
     * @return array
     */
    public function remove_table_on_delete_blog($tables)
    {

        foreach (array('sms_subscribes', 'sms_subscribes_group', 'sms_send') as $tbl) {
            $tables[] = $this->tb_prefix . $tbl;
        }

        return $tables;
    }
}

new Install();