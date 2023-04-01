<?php

namespace WP_SMS\Controller;

use Exception;
use WP_SMS\Helper;
use WP_SMS\Utils\CsvHelper;

class UploadSubscriberCsv extends AjaxControllerAbstract
{
    protected $action = 'wp_sms_upload_subscriber_csv';

    /**
     * @throws Exception
     */
    protected function run()
    {

        // Allowed mime types
        $file_mimes = array(
            'application/x-csv',
            'text/x-csv',
            'text/csv',
            'application/csv',
        );

        if (empty($_FILES["file"]["name"])) {
            throw new Exception(__('Choose a *.csv file, first.', 'wp-sms'));
        }

        // Validate whether selected file is a CSV file
        if (!in_array($_FILES['file']['type'], $file_mimes)) {
            throw new Exception(__("Only *.csv files are allowed.", 'wp-sms'));
        }

        // Open uploaded CSV file with read-only mode
        $csvFile = fopen($_FILES['file']['tmp_name'], 'r');

        if (empty(file($_FILES['file']['tmp_name']))) {
            throw new Exception(__("The uploaded file doesn't contain any data.", 'wp-sms'));
        }

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

        $destination = wp_upload_dir();
        $currentTime = gmdate('Y-m-d-H-i-s');
        $fileName    = sprintf('wp-sms-subscriber-%s.csv', $currentTime);
        $destination = $destination['path'] . '/' . $fileName;

        move_uploaded_file($_FILES['file']['tmp_name'], $destination);

        // Close opened CSV file
        fclose($csvFile);

        $first_row = json_encode($first_row);

        header("X-FirstRow-content: {$first_row}");

        if (!empty(get_option('wp_sms_import_file'))) {
            delete_option('wp_sms_import_file');
        }

        add_option('wp_sms_import_file', $fileName);

        wp_send_json_success(__('File uploaded successfully.', 'wp-sms'));

    }
}