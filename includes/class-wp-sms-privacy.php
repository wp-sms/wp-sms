<?php

/**
 * Class Privacy
 */
class Privacy {

	public $options;

	public function __construct() {
		global $wpsms_option;

        add_action( 'admin_notices', array( $this, 'admin_notification' ) );
        add_action( 'admin_init', array( $this, 'process_form' ) );
	}

    /**
     * Show Admin Notification
     *
     * @param  Not param
     */
    public function admin_notification()
    {
        global $pagenow;

        /*
         * privacy Page
         */
       if($pagenow =="admin.php" and $_GET['page'] =="wp-sms-subscribers-privacy") {

           if( isset($_GET['error']) ) {
               /*
                *  Empty Mobile Number
                */
               if($_GET['error'] =="empty_number") {
                   WP_SMS_Plugin::wp_admin_notice( __( 'Please enter the mobile number', 'wp-sms' ), "error");
               }

               /*
               *  Not found User
                */
               if($_GET['error'] =="not_found") {
                   WP_SMS_Plugin::wp_admin_notice( __( 'User with this mobile number was not found', 'wp-sms' ), "error");
               }
           }

           /*
            * Success Mobile Number
            */
           if( isset($_GET['delete_mobile']) ) {
               WP_SMS_Plugin::wp_admin_notice( sprintf(__('User with %s mobile number is removed completely', 'wp-sms'), trim($_GET['delete_mobile']) ) , "success");
           }

        }
    }

    /*
     * Process Privacy Form
     *
     */
    public function process_form()
    {
        global $pagenow;
        if($pagenow =="admin.php" and $_GET['page'] =="wp-sms-subscribers-privacy" and wp_verify_nonce( $_POST['wp_sms_nonce_privacy'], 'wp_sms_nonce_privacy' ) and isset($_POST['submit']) ) {

            $mobile = ($_POST['submit'] =="Export" ? sanitize_text_field($_POST['mobile-number-export']) : sanitize_text_field($_POST['mobile-number-delete']));

            //Is Empty Mobile Number
            $this->check_empty_mobile($mobile);

            //Check User Not Exist
            $user_data = $this->check_user_exist_mobile($mobile);

            /*
             * Export Area
             */
            if($_POST['submit'] =="Export") {
                $this->create_csv($user_data, "wp-sms-report-".$mobile);
            }

            /*
             * Delete Area
             */
            if($_POST['submit'] =="Delete") {
                wp_redirect( admin_url(add_query_arg( array('page' => 'wp-sms-subscribers-privacy', 'delete_mobile' => $mobile), 'admin.php' )) );
                exit;
            }

        }
    }


    /**
     * Check Mobile Number is Empty
     *
     * @param $mobile Mobile Number
     */
    public function check_empty_mobile($mobile)
    {
        if(empty($mobile)) {
            wp_redirect( admin_url(add_query_arg( array('page' => 'wp-sms-subscribers-privacy', 'error' => 'empty_number'), 'admin.php' )) );
            exit;
        }
    }


    /**
     * Check Exist User By Mobile A
     */
    public function check_user_exist_mobile($mobile)
    {
        global $wpdb;
        $result = array();

        /*
         * Check in Wordpress User
         */
        $get_user = get_users( array('meta_key' => 'mobile', 'meta_value' => $mobile, 'meta_compare' => '=', 'fields' => 'all_with_meta'));
        if( count($get_user) >0) {
            foreach ( $get_user as $user ) {
                //Get User Data
                $result[] = array("FullName" => $user->first_name." ".$user->last_name, "Mobile" => $user->mobile, "RegisterDate" => $user->user_registered );

                //Remove User data if Delete Request
                if($_POST['submit'] =="Delete") delete_user_meta( $user->ID, 'mobile' );
            }
        }

        /*
         * Check in Subscribes Table
         */
        $get_user = $wpdb->get_results("SELECT * FROM `{$wpdb->prefix}sms_subscribes` WHERE `mobile` = '$mobile'", ARRAY_A);
        if( count($get_user) >0) {
            foreach ( $get_user as $user ) {
                //Get User Data
                $result[] = array("FullName" => $user['name'], "Mobile" => $user['mobile'], "RegisterDate" => $user['date']);

                //Remove User data if Delete Request
                if($_POST['submit'] =="Delete") $wpdb->delete( $wpdb->prefix.'sms_subscribes', array( 'ID' => $user['ID'] ), array( '%d' ) );;
            }
        }

        if ( empty($result) ) {
            wp_redirect( admin_url(add_query_arg( array('page' => 'wp-sms-subscribers-privacy', 'error' => 'not_found'), 'admin.php' )) );
            exit;
        }

        return $result;
    }


    /**
     * Check Exist User With Mobile Meta data
     *
     * @param array  $data Mobile Number
     * @param string  $filename File Name
     * @return string export Force Download Csv File
     */
    public function create_csv($data, $filename)
    {
        $filepath = $_SERVER["DOCUMENT_ROOT"] . $filename.'.csv';
        $fp = fopen($filepath, 'w+');

        $i = 0;
        foreach ($data as $fields) {
            if($i == 0){
                fputcsv($fp, array_keys($fields));
            }
            fputcsv($fp, array_values($fields));
            $i++;
        }
        header('Content-Type: application/octet-stream; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        header('Content-Length: ' . filesize($filepath));
        echo file_get_contents($filepath);
        exit;
    }


}

new Privacy();