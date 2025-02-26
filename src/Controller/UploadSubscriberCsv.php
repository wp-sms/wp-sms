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
            'application/vnd.ms-excel', // Older Excel MIME type (sometimes used for CSV files)
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // Excel MIME type for modern CSV
            'text/comma-separated-values', // Common CSV MIME type
            'text/tsv', // Tab-separated values, sometimes used as CSV
        );

        if (empty($_FILES["file"]["name"])) {
            throw new Exception(esc_html__('Choose a *.csv file, first.', 'wp-sms'));
        }

        // Validate whether selected file is a CSV file
        if (!in_array($_FILES['file']['type'], $file_mimes)) {
            throw new Exception(esc_html__("Only *.csv files are allowed.", 'wp-sms'));
        }

        // Open uploaded CSV file with read-only mode
        $csvFile = fopen($_FILES['file']['tmp_name'], 'r'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

        if (empty(file($_FILES['file']['tmp_name']))) {
            throw new Exception(esc_html__("The uploaded file doesn't contain any data.", 'wp-sms'));
        }

        // check whether file includes header
        $has_header = sanitize_text_field($_GET['hasHeader']);

        // if the file contains header, skip the first line and if not,
        // choose the first line as an index to show to client
        if (isset($has_header) and $has_header == 'true') {
            for ($i = 0; $i <= 1; $i++) {
                $first_row = fgetcsv($csvFile);
            }
        } else {
            $first_row = fgetcsv($csvFile);
        }

        // Call wp_handle_upload() to handle file upload
        $upload_overrides = array('test_form' => false);
        $upload_result    = wp_handle_upload($_FILES['file'], $upload_overrides);

        // Check if there's an error during upload
        if (isset($upload_result['error'])) {
            throw new Exception("Error uploading file: " . esc_html($upload_result['error']));
        }

        // Get the uploaded file path
        $uploaded_file_path = $upload_result['file'];

        // Close opened CSV file
        fclose($csvFile); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

        $first_row = wp_json_encode($first_row);

        header("X-FirstRow-content: {$first_row}");

        // Delete old option if exists and add new option
        if (!empty(get_option('wp_sms_import_file'))) {
            delete_option('wp_sms_import_file');
        }
        add_option('wp_sms_import_file', basename($uploaded_file_path));

        // Send JSON response
        wp_send_json_success(esc_html__('File uploaded successfully.', 'wp-sms'));
    }

}