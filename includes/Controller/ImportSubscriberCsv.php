<?php

namespace WP_SMS\Controller;

use Exception;
use WP_SMS\Newsletter;
use WP_SMS\Utils\CsvHelper;

class ImportSubscriberCsv extends AjaxControllerAbstract {
	protected $action = 'wp_sms_import_subscriber';

	protected function run() {
		try {

			//get index of each required parameter for adding a new subscriber
			$name_index   = $this->get( 'name' );
			$mobile_index = $this->get( 'mobile' );
			$group        = $this->get( 'group' );
			$state        = $this->get( 'state' );
			$has_header   = $this->get( 'hasHeader' );

			//find the uploaded file (the last added file to the upload folder)
			//and read its content
			$destination = wp_upload_dir();
			$files       = scandir( $destination['path'], SCANDIR_SORT_DESCENDING );
			$destination = $destination['path'] . '/' . $files[0];
			$csvFile     = file( $destination );

			$lines   = count( $csvFile );
			$counter = 0;
			$result  = [];

			if ( $has_header ) {
				$lines   -= 1;
				$counter = 1;
			}

			if ( $state == 'new_group' ) {
				$result = Newsletter::addGroup( $group );
				$group  = $result['data']['group_ID'];
			}

			while ( $counter <= $lines ) {
				$array = explode( ',', $csvFile[ $counter ] );

				if ( $state ) {
					$result[] = Newsletter::addSubscriber( $array[ $name_index ], $array[ $mobile_index ], $group );
				} else {
					$result[] = Newsletter::addSubscriber( $array[ $name_index ], $array[ $mobile_index ], $array[ $group ] );
				}

				$counter ++;
			}

		} catch ( Exception $e ) {
			wp_send_json_error( $e->getMessage(), $e->getCode() );
		}

		// delete the file
		// todo
	}
}