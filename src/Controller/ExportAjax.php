<?php

namespace WP_SMS\Controller;

use Exception;
use WP_SMS\Utils\CsvHelper;

class ExportAjax extends AjaxControllerAbstract {
	protected $action = 'wp_sms_export';

	/**
	 * @var array[]
	 */
	private $columns = [
		'subscriber' => [
			'ID',
			'date',
			'name',
			'mobile',
			'status',
			'group_ID'
		],
		'outbox'     => [
			'ID',
			'date',
			'sender',
			'message',
			'recipient',
			'media',
			'response',
			'status'
		],
		'inbox'      => [
			'id',
			'sender_number',
			'gateway',
			'text',
			'media',
			'command_id',
			'command_name',
			'command_args',
			'action_data',
			'action_status',
			'received_at',
			'read_at',
			'deleted_at'
		]
	];

	protected function run() {
		global $wpdb;

		$type       = $this->get( 'type' );
		$columns    = $this->columns[ $type ];
		$collection = [];

		/*
		 * 1. Collect the data
		 */
		switch ( $type ) {
			case 'subscriber':
				$collection = \WP_SMS\Newsletter::getSubscribers( $this->get( 'groupIds' ), false, $columns );

				break;

			case 'outbox':
				$collection = $wpdb->get_results(
                    $wpdb->prepare( "SELECT * from {$wpdb->prefix}sms_send" )
                );

				break;

			case 'inbox':
				$collection = $wpdb->get_results(
                    $wpdb->prepare( "SELECT * from {$wpdb->prefix}sms_two_way_incoming_messages" )
                );

				break;
		}

		if ( isset( $collection ) && $collection ) {

			// convert object to array
			$collection      = json_decode( wp_json_encode( $collection ), true );
			$columns_array[] = $columns;
			$collection      = array_merge_recursive( $columns_array, $collection );
			$current_time    = gmdate( 'Y-m-d-H-i-s' );

			/*
			 * 2. Generate the CSV
			 */
			$file_name = sprintf( 'wp-sms-export-%s-%s.csv', $type, $current_time );

			$csvHelper = new CsvHelper();
			$csvHelper->array2csv( $file_name, $collection, true );

		} else {
			wp_send_json_error( __( 'There is no data to export' ), 'wp-sms' );
		}
	}
}