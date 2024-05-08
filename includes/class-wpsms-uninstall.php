<?php

namespace WP_SMS;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

class Uninstall
{
    /**
     * Deactivate plugin
     */
    public function deactivate()
    {
        $this->clearEvents();
        // add more here
    }

    public function clearEvents()
    {
        // Remove any scheduled cron jobs
        $wpSmsCronEvents = array(
            'wp_sms_check_update_licenses_status',
            'wp_sms_admin_email_report',
        );

        foreach ($wpSmsCronEvents as $event) {
            wp_clear_scheduled_hook($event);
        }
    }
}
