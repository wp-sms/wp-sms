<?php

namespace WP_SMS\Controller;

use Exception;
use WP_SMS\Helper;
use WP_SMS\Newsletter;
use WP_SMS\Utils\CsvHelper;

class ImportSubscriberCsv extends AjaxControllerAbstract
{
    protected $action = 'wp_sms_import_subscriber';
    public $requiredFields = [
        'name',
        'mobile',
    ];

    /**
     * @throws Exception
     */
    protected function run()
    {

        //find the uploaded file in the session
        $destination = wp_upload_dir();
        $file        = get_option('wp_sms_import_file');
        $destination = $destination['path'] . '/' . $file;
        $data        = file($destination);

        $start_point = $this->get('startPoint');

        // Get index of each required parameter for adding a new subscriber
        $name_index   = $this->get('name');
        $mobile_index = $this->get('mobile');
        $group        = $this->get('group');
        $state        = $this->get('state');
        $has_header   = $this->get('hasHeader');

        if ($start_point == 0) {
            // Check whether file uploaded
            if (empty($data)) {
                throw new Exception(esc_html__('There is no file to import. Please try again to upload the file.', 'wp-sms'));
            }

            if ($state == 'new_group') {
                $result = Newsletter::addGroup($group);
                $group  = $result['data']['group_ID'];
            }
        }

        if (isset($has_header) && $has_header) {
            array_shift($data);
        }

        // Break the loop when the import completed
        if (count($data) <= $start_point) {
            //delete the uploaded file
            unlink($destination);

            wp_send_json_success([
                'importDone' => true,
                'count'      => count($data),
            ]);
        }

        $offset = 50;
        $lines  = array_slice($data, $start_point, $offset);

        $counter        = 0;
        $success_upload = 0;
        $errors         = [];

        foreach ($lines as $line) {
            $array         = explode(',', $line);
            $mobile_number = $array[$mobile_index];

            // check whether the group id is chosen from the CSV file by the client
            // or it is created or chosen in the front-end
            if ($state) {
                $group_id = $group;
            } else {
                $group_id = $array[$group];
            }

            // check group id validity
            $selected_group = Newsletter::getGroup($group_id);
            if (!isset($selected_group)) {
                $errors[$mobile_number] = __('The group ID is not valid', 'wp-sms');
                $counter++;
                continue;
            }

            // check mobile number validity
            $check_validity = Helper::checkMobileNumberValidity($mobile_number, false, true, $group_id, false);

            if (is_wp_error($check_validity)) {
                $errors[$mobile_number] = $check_validity->get_error_message();
                $counter++;
                continue;
            }

            $result = Newsletter::addSubscriber($array[$name_index], $array[$mobile_index], $group_id);

            if ($result['result'] == 'error') {
                $errors[$mobile_number] = $result['message'];
                $counter++;
                continue;
            }

            $counter++;
            $success_upload++;
        }

        /**
         * Return response
         */
        wp_send_json_success([
            'startPoint'    => $start_point + $counter,
            'importDone'    => false,
            'count'         => count($data),
            'offset'        => $offset,
            'errors'        => $errors,
            'successUpload' => $success_upload
        ]);

        delete_option('wp_sms_import_file');
    }
}