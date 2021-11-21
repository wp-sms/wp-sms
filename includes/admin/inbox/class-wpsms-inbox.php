<?php

namespace WP_SMS;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

// Inbox page class
class Inbox
{
    /**
     * Inbox sms admin page
     */
    public function render_page()
    {
        $renderCallback = apply_filters('wp_sms_admin_inbox_render_callback', function () {
            include_once WP_SMS_DIR . "includes/admin/inbox/inbox.php";
        });

        call_user_func($renderCallback);
    }
}
