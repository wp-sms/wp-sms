<?php

namespace WP_SMS\Utils;

use WP_SMS\Helper;
use WP_SMS\Version;

class GenerateReport
{
    public $lastWeek;

    public function __construct()
    {
        $this->lastWeek = date('Y-m-d H:i:s', strtotime('-1 week'));
    }

    public function generate()
    {
        if (isset($this->options['report_wpsms_statistics']) && !$this->options['report_wpsms_statistics']) {
            return;
        }

        // Gather report data
        $smsData          = $this->getSMSData();
        $subscriptionData = $this->getSubscriptionData();
        $loginData        = $this->getLoginData();
        $duration         = $this->getTheDuration();

        // Get email needed templates and variables
        $reportData       = apply_filters('wp_sms_report_email_data', Helper::loadTemplate('email/report-data.php', [
            'sms_data'          => $smsData,
            'subscription_data' => $subscriptionData,
            'login_data'        => $loginData,
            'duration'          => $duration
        ]));
        $content          = apply_filters('wp_sms_report_email_content', Helper::loadTemplate('email/report-content.php'));
        $proAdvertisement = Helper::loadTemplate('email/pro-advertisement.php');
        $siteName         = get_bloginfo('name');
        $subject          = sprintf(__('%s - SMS Report', 'wp-sms'), $siteName);

        // Do this action before sending report email
        do_action('wp_sms_before_report_email', $reportData, $content);

        // Send Email
        Helper::sendMail($subject, [
            'email_title'       => __('SMS Report', 'wp-sms'),
            'content'           => $content,
            'report_data'       => $reportData,
            'pro_advertisement' => $proAdvertisement,
            'site_url'          => home_url(),
            'site_name'         => $siteName,
            'pro_is_active'     => Version::pro_is_active(),
        ]);
    }

    public function getLoginData()
    {
        $loginData = [
            'failed'  => 0,
            'success' => 0,
            'total'   => 0
        ];

        global $wpdb;
        $table_name = $wpdb->prefix . 'sms_otp_attempts';
        $lastWeek   = strtotime('-1 week');

        // SQL query to select rows from the last week
        $query   = $wpdb->prepare("SELECT * FROM $table_name WHERE time >= %d AND time <= %d", $lastWeek, current_time('timestamp'));
        $results = $wpdb->get_results($query);

        foreach ($results as $result) {
            if ($result->result == 0) {
                $loginData['failed']++;
            }
        }

        $loginData['total']   = count($results);
        $loginData['success'] = $loginData['total'] - $loginData['failed'];
        return $loginData;
    }

    public function getSubscriptionData()
    {
        $subscriptionData = [
            'groups'              => [],
            'total'               => 0,
            'activeSubscribers'   => 0,
            'deactiveSubscribers' => 0,
        ];

        global $wpdb;
        $table_subscribes = $wpdb->prefix . 'sms_subscribes';
        $table_groups     = $wpdb->prefix . 'sms_subscribes_group';

        // SQL query to select rows from the last week and join with the groups table
        $query = $wpdb->prepare("  SELECT s.*, g.name  FROM $table_subscribes AS s  LEFT JOIN $table_groups AS g ON s.group_ID = g.ID  WHERE s.date >= %s AND s.date <= %s  ", $this->lastWeek, current_time('mysql'));

        $results = $wpdb->get_results($query);

        foreach ($results as $result) {
            $groupID = $result->group_ID;

            // Check if the group_ID exists in $subscriptionData['groups']
            if (!isset($subscriptionData['groups'][$groupID])) {
                // Initialize the group with 'active' and 'deactive' counts and group name
                $subscriptionData['groups'][$groupID] = [
                    'name'     => $result->name ? $result->name : __('General', 'wp-sms'),
                    'active'   => 0,
                    'deactive' => 0,
                ];
            }

            if ($result->status == 1) {
                $subscriptionData['groups'][$groupID]['active']++;
                $subscriptionData['activeSubscribers']++;
            } else {
                $subscriptionData['groups'][$groupID]['deactive']++;
                $subscriptionData['deactiveSubscribers']++;
            }
        }

        $subscriptionData['total'] = count($results);
        return $subscriptionData;
    }

    public function getSMSData()
    {
        $smsData = [
            'success' => 0,
            'failed'  => 0,
            'total'   => 0,
        ];

        global $wpdb;
        $table_name = $wpdb->prefix . 'sms_send';

        // SQL query to select rows from the last week
        $query   = $wpdb->prepare(" SELECT * FROM $table_name WHERE date >= %s AND date <= %s", $this->lastWeek, current_time('mysql'));
        $results = $wpdb->get_results($query);

        foreach ($results as $result) {
            if ($result->status === 'success') {
                $smsData['success']++;
            }
        }

        $smsData['total']  = count($results);
        $smsData['failed'] = $smsData['total'] - $smsData['success'];
        return $smsData;
    }

    public function getTheDuration()
    {
        $first_day_option = get_option('start_of_week', 0);

        // Calculate the first and last day of the previous week
        $now                = current_time('timestamp');
        $firstDayOfLastWeek = strtotime('last sunday', $now) - ($first_day_option * 86400);
        $lastDayOfLastWeek  = strtotime('last saturday', $now) - ($first_day_option * 86400);

        // Convert Unix timestamps to the desired date format
        return ['startDate' => date('j M', $firstDayOfLastWeek), 'endDate' => date('j M', $lastDayOfLastWeek)];
    }
}