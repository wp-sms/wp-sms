<?php

namespace WP_SMS\Services\Report;

use WP_SMS\Helper;
use WP_SMS\Option;
use WP_SMS\Version;

class EmailReportGenerator
{
    public $lastWeek;
    private $db;

    public function __construct()
    {
        global $wpdb;

        $this->lastWeek = date('Y-m-d H:i:s', strtotime('-1 week')); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date	
        $this->db       = $wpdb;
    }

    public function generate()
    {
        if (!Option::getOption('report_wpsms_statistics')) {
            wp_clear_scheduled_hook('wp_sms_admin_email_report');
            return;
        }

        // Gather report data
        $smsData          = $this->getSmsData();
        $subscriptionData = $this->getSubscriptionData();
        $loginData        = $this->getLoginData();
        $duration         = $this->getTheDuration();

        // return if no data
        if ($smsData['total'] === 0 && $subscriptionData['total'] === 0 && $loginData['total'] === 0) {
            return;
        }

        // Get email needed templates and variables
        $reportData       = apply_filters('wp_sms_report_email_data', Helper::loadTemplate('email/partials/report-data.php', [
            'sms_data'          => $smsData,
            'subscription_data' => $subscriptionData,
            'login_data'        => $loginData,
            'duration'          => $duration
        ]));
        $content          = apply_filters('wp_sms_report_email_content', '');
        $footerSuggestion = !Version::pro_is_active() ? Helper::loadTemplate('email/partials/footer-suggestion.php') : '';
        $siteName         = get_bloginfo('name');
        // translators: %s: Site name
        $subject          = sprintf(__('%s - SMS Report', 'wp-sms'), $siteName);

        // Do this action before sending report email
        do_action('wp_sms_before_report_email', $reportData, $content);

        // Send Email
        Helper::sendMail($subject, [
            'email_title'       => __('SMS Report', 'wp-sms'),
            'content'           => $content,
            'report_data'       => $reportData,
            'footer_suggestion' => $footerSuggestion,
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

        $table_name = $this->db->prefix . 'sms_otp_attempts';
        $lastWeek   = strtotime('-1 week');

        // SQL query to select rows from the last week
        $query   = $this->db->prepare("SELECT * FROM $table_name WHERE time >= %d AND time <= %d", $lastWeek, current_time('timestamp'));
        $results = $this->db->get_results($query);

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

        $table_subscribes = $this->db->prefix . 'sms_subscribes';
        $table_groups     = $this->db->prefix . 'sms_subscribes_group';

        // SQL query to select rows from the last week and join with the groups table
        $query = $this->db->prepare("SELECT s.*, g.name  FROM $table_subscribes AS s  LEFT JOIN $table_groups AS g ON s.group_ID = g.ID  WHERE s.date >= %s AND s.date <= %s  ", $this->lastWeek, current_time('mysql'));

        $results = $this->db->get_results($query);

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

    public function getSmsData()
    {
        $smsData = [
            'success' => 0,
            'failed'  => 0,
            'total'   => 0,
        ];

        $table_name = $this->db->prefix . 'sms_send';

        // SQL query to select rows from the last week
        $query   = $this->db->prepare(" SELECT * FROM $table_name WHERE date >= %s AND date <= %s", $this->lastWeek, current_time('mysql'));
        $results = $this->db->get_results($query);

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
        // Calculate the first and last day of the previous week
        $firstDayOfLastWeek = strtotime($this->lastWeek);
        $lastDayOfLastWeek  = current_time('timestamp');

        // Convert Unix timestamps to the desired date format
        return ['startDate' => date('j M', $firstDayOfLastWeek), 'endDate' => date('j M Y', $lastDayOfLastWeek)]; // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
    }
}