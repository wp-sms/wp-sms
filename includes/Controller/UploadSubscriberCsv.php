<?php

namespace WP_SMS\Controller;

use Exception;
use WP_SMS\Utils\CsvHelper;

class UploadSubscriberCsv extends AjaxControllerAbstract
{
    protected $action = 'wp_sms_upload_subscriber_csv';

    protected function run()
    {
        try {

            if (empty($_FILES["file"]["name"])) {
                throw new Exception('No file is uploaded.');
            }

            // Allowed mime types
            $file_mimes = array(
                'application/x-csv',
                'text/x-csv',
                'text/csv',
                'application/csv',
            );

            // Validate whether selected file is a CSV file
            if (!empty($_FILES['file']['name']) && in_array($_FILES['file']['type'], $file_mimes)) {

                // Open uploaded CSV file with read-only mode
                $csvFile = fopen($_FILES['file']['tmp_name'], 'r');

                // check whether file includes header
                $has_header = $_GET['hasHeader'];

                // if the file contains header, skip the first line and if not,
                // choose the first line as an index to show to client
                if (isset($has_header) and $has_header == 'true') {
                    for ($i = 0; $i <= 1; $i++) {
                        $first_row = fgetcsv($csvFile);
                    }
                } else {
                    $first_row = fgetcsv($csvFile);
                }

				$fileName = $_FILES['file']['name'];

                $destination = wp_upload_dir();
                $destination = $destination['path'] . '/' . $_FILES['file']['name'];

                move_uploaded_file($_FILES['file']['tmp_name'], $destination);

                // Close opened CSV file
                fclose($csvFile);

                $first_row = json_encode($first_row);

                header("FirstRow-content: {$first_row}");
                header("X-FirstTempPath-content: {$_FILES['file']['tmp_name']}");
                header("X-FirstName-content: {$first_row}");

            }
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage(), $e->getCode());
        }
    }
}