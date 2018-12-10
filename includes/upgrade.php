<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( is_admin() ) {

	$installer_wpsms_ver = get_option( 'wp_sms_db_version' );

	if ( $installer_wpsms_ver < WP_SMS_VERSION ) {

		global $wpdb;
		$wpdb->query( "ALTER TABLE {$wpdb->prefix}sms_send
			 ADD status varchar(10) NOT NULL AFTER recipient,
			 ADD response TEXT NOT NULL AFTER recipient" );

		update_option( 'wp_sms_db_version', WP_SMS_VERSION );
	}
}
