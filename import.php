<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

include_once dirname( __FILE__ ) . "/includes/classes/excel-reader.class.php";

global $wpdb, $table_prefix;
$get_mobile = $wpdb->get_col( "SELECT `mobile` FROM {$table_prefix}sms_subscribes" );
$result     = [];
$duplicate  = [];

if ( isset( $_POST['wps_import'] ) ) {
	if ( ! $_FILES['wps-import-file']['error'] ) {

		$data = new Spreadsheet_Excel_Reader( $_FILES["wps-import-file"]["tmp_name"] );

		foreach ( $data->sheets[0]['cells'] as $items ) {

			// Check and count duplicate items
			if ( in_array( $items[2], $get_mobile ) ) {
				$duplicate[] = $items[2];
				continue;
			}

			// Count submitted items.
			$total_submit[] = $data->sheets[0]['cells'];

			$result = $wpdb->insert( "{$table_prefix}sms_subscribes",
				array(
					'date'     => WP_SMS_CURRENT_DATE,
					'name'     => $items[1],
					'mobile'   => $items[2],
					'status'   => '1',
					'group_ID' => $_POST['wpsms_group_name']
				)
			);

		}

		if ( $result ) {
			echo "<div class='updated'><p>" . sprintf( __( '<strong>%s</strong> items was successfully added.', 'wp-sms' ), count( $total_submit ) ) . "</div></p>";
		}

		if ( $duplicate ) {
			echo "<div class='error'><p>" . sprintf( __( '<strong>%s</strong> Mobile numbers Was repeated.', 'wp-sms' ), count( $duplicate ) ) . "</div></p>";
		}

	} else {
		echo "<div class='error'><p>" . __( 'Please complete all fields', 'wp-sms' ) . "</div></p>";
	}
}