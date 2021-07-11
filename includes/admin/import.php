<?php

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

include_once WP_SMS_DIR . "includes/libraries/excel-reader.class.php";

global $wpdb;

$get_mobile      = array();
$get_mobile_dups = array();

if (isset($_POST['ignore_duplicate']) and $_POST['ignore_duplicate'] == 'ignore') {
    $get_mobile      = \WP_SMS\Newsletter::getSubscribers($_POST['wpsms_group_name']);
    $get_mobile_dups = \WP_SMS\Newsletter::getSubscribers();
} else {
    $get_mobile = \WP_SMS\Newsletter::getSubscribers();
}

$result          = [];
$duplicate       = [];
$count_duplicate = [];
$total_submit    = [];

if (isset($_POST['wps_import'])) {
    if (isset($_FILES['wps-import-file']) and !$_FILES['wps-import-file']['error']) {

        $data = new Spreadsheet_Excel_Reader($_FILES["wps-import-file"]["tmp_name"]);

        foreach ($data->sheets[0]['cells'] as $items) {

            // Check and count duplicate items
            if (in_array($items[2], $get_mobile)) {
                $duplicate[] = $items[2];
                continue;
            }

            if (isset($_POST['ignore_duplicate']) and $_POST['ignore_duplicate'] == 'ignore') {
                //Count only imported Duplicate items
                if (in_array($items[2], $get_mobile_dups)) {
                    $count_duplicate[] = $items[2];
                }
            }

            // Count submitted items.
            $total_submit[] = $data->sheets[0]['cells'];

            $wpsms_group_name = sanitize_text_field($_POST['wpsms_group_name']);
            $result           = \WP_SMS\Newsletter::insertSubscriber(WP_SMS_CURRENT_DATE, $items[1], $items[2], 1, $wpsms_group_name);

        }

        if ($result) {
            if (isset($_POST['ignore_duplicate']) and $_POST['ignore_duplicate'] == 'ignore') {
                echo " <div class='updated'><p > " . sprintf(__('<strong>%s</strong> items was successfully added and There was <strong>%s</strong> duplicated numbers.', 'wp-sms'), count($total_submit), count($count_duplicate)) . "</div ></p > ";
            } else {
                echo " <div class='updated' ><p >" . sprintf(__('<strong>%s</strong> items was successfully added.', 'wp-sms'), count($total_submit)) . "</div ></p>";
            }
        }

        if ($duplicate) {
            echo "<div class='error'><p>" . sprintf(__('<strong>%s</strong> Mobile numbers Was repeated.', 'wp-sms'), count($duplicate)) . "</div ></p>";
        }

    } else {
        echo "<div class='error'><p> " . __('Please complete all fields', 'wp-sms') . "</div ></p>";
    }
}