<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * Class WP_SMS_INSTALL
 */
class WP_SMS_INSTALL {

	/**
	 * Table SQL
	 *
	 * @param  Not param
	 */
	public function table_sql() {
		global $wpdb;
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$table_name = $wpdb->prefix . 'sms_subscribes';
		if ( $wpdb->get_var( "show tables like '{$table_name}'" ) != $table_name ) {
			$create_sms_subscribes = ( "CREATE TABLE IF NOT EXISTS {$table_name}(
            ID int(10) NOT NULL auto_increment,
            date DATETIME,
            name VARCHAR(20),
            mobile VARCHAR(20) NOT NULL,
            status tinyint(1),
            activate_key INT(11),
            group_ID int(5),
            PRIMARY KEY(ID)) CHARSET=utf8" );

			dbDelta( $create_sms_subscribes );
		}

		$table_name = $wpdb->prefix . 'sms_subscribes_group';
		if ( $wpdb->get_var( "show tables like '{$table_name}'" ) != $table_name ) {
			$create_sms_subscribes_group = ( "CREATE TABLE IF NOT EXISTS {$table_name}(
            ID int(10) NOT NULL auto_increment,
            name VARCHAR(250),
            PRIMARY KEY(ID)) CHARSET=utf8" );

			dbDelta( $create_sms_subscribes_group );
		}

		$table_name = $wpdb->prefix . 'sms_send';
		if ( $wpdb->get_var( "show tables like '{$table_name}'" ) != $table_name ) {
			$create_sms_send = ( "CREATE TABLE IF NOT EXISTS {$table_name}(
            ID int(10) NOT NULL auto_increment,
            date DATETIME,
            sender VARCHAR(20) NOT NULL,
            message TEXT NOT NULL,
            recipient TEXT NOT NULL,
  			response TEXT NOT NULL,
  			status varchar(10) NOT NULL,
            PRIMARY KEY(ID)) CHARSET=utf8" );

			dbDelta( $create_sms_send );
		}
	}


	/**
	 * Adding new MYSQL Table in Activation Plugin
	 *
	 * @param  Not param
	 */
	public function create_table( $network_wide ) {
		global $wpdb;

		if ( is_multisite() && $network_wide ) {
			$blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
			foreach ( $blog_ids as $blog_id ) {
				switch_to_blog( $blog_id );
				$this->table_sql();
				restore_current_blog();
			}
		} else {
			$this->table_sql();
		}

	}

}
