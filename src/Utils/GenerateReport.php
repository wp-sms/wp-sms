<?php

namespace WP_SMS\Utils;

use WP_SMS\Helper;

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

        $smsData          = $this->getSMSData();
        $subscriptionData = $this->getSubscriptionData();
        $loginData        = $this->getLoginData();
        $duration         = $this->getTheDuration();

        $siteName = get_bloginfo('name');
        $args     = [
            'email_title'      => __('SMS Delivery Issue', 'wp-sms'),
            'content'          => 'hi world!',
            'site_url'         => home_url(),
            'smsData'          => $smsData,
            'subscriptionData' => $subscriptionData,
            'loginData'        => $loginData,
            'duration'         => $duration,
            'site_url'         => home_url(),
            'site_name'        => $siteName,
        ];

        $subject    = sprintf(__('%s - SMS Report', 'wp-sms'), $siteName);
        $adminEmail = get_option('admin_email');
        $message    = Helper::loadTemplate('email/report.php', $args);
        $headers    = array('Content-Type: text/html; charset=UTF-8');

        return wp_mail($adminEmail, $subject, $message, $headers);
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