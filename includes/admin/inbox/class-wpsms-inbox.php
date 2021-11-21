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
        $templateFile = apply_filters('wp_sms_admin_inbox_template_path', WP_SMS_DIR . "includes/admin/inbox/inbox.php");

        include_once $templateFile;
    }
}
