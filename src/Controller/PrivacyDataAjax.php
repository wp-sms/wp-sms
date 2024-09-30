<?php

namespace WP_SMS\Controller;

use WP_SMS\Helper;

class PrivacyDataAjax extends AjaxControllerAbstract
{
    protected $action = 'wp_sms_privacy_data';
    protected $options;
    protected $mobile;
    protected $type;

    protected function run()
    {
        $this->mobile = (int)$this->get('mobileNumber');
        $this->type   = $this->get('type');

        // Process the submitted form
        $this->processForm();
    }

    /*
    * Process Privacy Form
    */
    public function processForm()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => Helper::notice(__('Sorry, you do not have permission to perform this action!', 'wp-sms'), 'error', false, '', true)), 400);
        }

        //Is Empty Mobile Number
        if (empty($this->mobile)) {
            wp_send_json_error(array('message' => Helper::notice(__('Please enter the mobile number!', 'wp-sms'), 'error', false, '', true)), 400);
        }

        //Check User Not Exist
        $user_data = $this->checkUserExistMobile($this->mobile);

        /*
         * Export type
         */
        if ($this->type === 'export') {
            $this->createCSV($user_data, "wp-sms-report-" . $this->mobile);
        }

        /*
         * Delete type
         */
        if ($this->type === 'delete') {
            wp_send_json_success(array('message' => Helper::notice(sprintf(__('User with % s mobile number is removed completely!', 'wp-sms'), $this->mobile), 'success', false, '', true)));
        }
    }

    /**
     * Check Exist User By Mobile A
     */
    public function checkUserExistMobile($mobile)
    {
        global $wpdb;
        $result = array();

        $get_user = get_users([
            'meta_key'   => Helper::getUserMobileFieldName(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            'meta_value' => Helper::prepareMobileNumberQuery($mobile) // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
        ]);

        if (count($get_user) > 0) {
            foreach ($get_user as $user) {
                $result[] = array(
                    'ID'            => $user->ID,
                    'Table'         => "{$wpdb->prefix}users",
                    'Display Name'  => $user->first_name . " " . $user->last_name,
                    'Mobile Number' => $mobile,
                    'Created At'    => $user->user_registered
                );

                //Remove User data if Delete Request
                if ($this->type === 'delete') {
                    delete_user_meta($user->ID, Helper::getUserMobileFieldName());
                }
            }
        }

        /*
         * Check in Subscribes Table
         */
        $get_user = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM `{$wpdb->prefix}sms_subscribes` WHERE `mobile` = %s", $mobile),
            ARRAY_A
        );

        if (count($get_user) > 0) {
            foreach ($get_user as $user) {
                //Get User Data
                $result[] = array(
                    'ID'            => $user->ID,
                    'Table'         => "{$wpdb->prefix}sms_subscribes",
                    'Display Name'  => $user['name'],
                    'Mobile Number' => $user['mobile'],
                    'Created At'    => $user['date']
                );

                //Remove User data if Delete Request
                if ($this->type === 'delete') {
                    $wpdb->delete($wpdb->prefix . 'sms_subscribes', array('ID' => $user['ID']), array(' % d'));
                }
            }
        }

        if (empty($result)) {
            wp_send_json_error(array('message' => Helper::notice(__('User with this mobile number was not found!', 'wp-sms'), 'error', false, '', true)), 400);
        }

        return $result;
    }


    /**
     * Creates CSV file
     *
     * @param array $data Mobile Number
     * @param string $filename File Name
     *
     * @return string export Force Download Csv File
     */
    public function createCSV($data, $filename)
    {
        $upload_dir        = wp_upload_dir();
        $uploads_directory = $upload_dir['basedir'];
        $filepath          = $uploads_directory . '/' . $filename . '.csv';

        $fp = fopen($filepath, 'w + '); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

        $i = 0;
        foreach ($data as $fields) {
            if ($i == 0) {
                fputcsv($fp, array_keys($fields));
            }
            fputcsv($fp, array_values($fields));
            $i++;
        }
        fclose($fp); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        $file_url = $upload_dir['baseurl'] . '/' . $filename . '.csv';

        wp_send_json_success([
            'message'  => Helper::notice(__('The CSV file generated successfully!', 'wp-sms'), 'success', false, '', true),
            'file_url' => $file_url
        ]);

        unlink($filepath);
        exit();
    }
}