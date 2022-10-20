<?php

namespace WP_SMS\Controller;

use Exception;
use WP_SMS\Helper;
use WP_SMS\Newsletter;
use WP_SMS\Utils\CsvHelper;

class ImportSubscriberCsv extends AjaxControllerAbstract {
	protected $action = 'wp_sms_import_subscriber';
	public $requiredFields = [
		'name',
		'mobile',
	];

	/**
	 * @throws Exception
	 */
	protected function run() {

		// Start session
		Helper::maybeStartSession();

		//find the uploaded file in the session
		$destination = wp_upload_dir();
		$file        = $_SESSION['wp_sms_import_file'];
		$destination = $destination['path'] . '/' . $file;
		$data        = file( $destination );

		$start_point = $this->get( 'startPoint' );

		// Get index of each required parameter for adding a new subscriber
		$name_index   = $this->get( 'name' );
		$mobile_index = $this->get( 'mobile' );
		$group        = $this->get( 'group' );
		$state        = $this->get( 'state' );
		$has_header   = $this->get( 'hasHeader' );

		if ( $start_point == 0 ) {

			// Check whether file uploaded
			if ( empty( $data ) ) {
				throw new Exception( __( 'There is no file to import. Please try again to upload the file.', 'wp-sms' ) );
			}

			if ( $state == 'new_group' ) {
				$result = Newsletter::addGroup( $group );
				$group  = $result['data']['group_ID'];
			}
		}

		if ( isset( $has_header ) && $has_header ) {
			array_shift( $data );
		}


		// Break the loop when the import completed
		if ( count( $data ) <= $start_point ) {
			//delete the uploaded file
			unlink( $destination );

			wp_send_json_success( [
				'importDone' => true,
				'message'    => 'All data imported successfully!'
			] );
		}

		$offset = 50;
		$lines  = array_slice( $data, $start_point, $offset );

		/**
		 * Import data
		 */
		$counter = 0;
		$error   = [];

		foreach ( $lines as $line ) {
			$array         = explode( ',', $line );
			$mobile_number = $array[ $mobile_index ];

			// todo
			/*if ( preg_match( '/^[0-9]{10}+$/', $mobile_number ) ) {
				$error[ $mobile_number ] = __( "Wrong number format.", 'wp-sms' );
				$counter ++;
				continue;
			}*/

			if ( $state ) {
				$group_state = $group;
			} else {
				$group_state = $array[ $group ];

				$selected_group = Newsletter::getGroup( $array[ $group ] );
				if ( ! isset( $selected_group ) ) {
					$error[ $mobile_number ] = __( "The selected group ID doesn't exist.", 'wp-sms' );
					$counter ++;
					continue;
				}
			}

			$result = Newsletter::addSubscriber( $array[ $name_index ], $array[ $mobile_index ], $group_state );

			if ( $result['result'] == 'error' ) {
				$error[ $mobile_number ] = $result['message'];
				$counter ++;
				continue;
			}

			$counter ++;
		}

		/**
		 * Return response
		 */
		wp_send_json_success( [
			'startPoint' => $start_point + $counter,
			'importDone' => false,
			'count'      => count( $data ),
			'offset'     => $offset,
			'error'      => $error
		] );
	}
}