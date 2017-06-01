<?php
require( '../../../wp-load.php' );

if ( ! is_super_admin() ) {
	wp_die( __( 'Access denied!', 'wp-sms' ) );
}

$type = $_POST['export-file-type'];

if ( $type ) {

	global $wpdb, $table_prefix;

	require( 'includes/classes/php-export-data.class.php' );

	$file_name = date( 'Y-m-d_H-i' );

	$result = $wpdb->get_results( "SELECT `ID`,`date`,`name`,`mobile`,`status`,`group_ID` FROM {$table_prefix}sms_subscribes" );

	switch ( $type ) {
		case 'excel':
			$exporter = new ExportDataExcel( 'browser', "{$file_name}.xls" );
			break;

		case 'xml':
			$exporter = new ExportDataExcel( 'browser', "{$file_name}.xml" );
			break;

		case 'csv':
			$exporter = new ExportDataCSV( 'browser', "{$file_name}.csv" );
			break;

		case 'tsv':
			$exporter = new ExportDataTSV( 'browser', "{$file_name}.tsv" );
			break;
	}

	$exporter->initialize();

	foreach ( $result[0] as $key => $col ) {
		$columns[] = $key;
	}
	$exporter->addRow( $columns );

	foreach ( $result as $row ) {
		$exporter->addRow( $row );
	}

	$exporter->finalize();

} else {
	wp_die( __( 'Please select the desired items.', 'wp-sms' ), false, array( 'back_link' => true ) );
}
?>