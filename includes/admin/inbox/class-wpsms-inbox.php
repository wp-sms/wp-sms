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
            echo Helper::loadTemplate('admin/inbox.php');
        });

        call_user_func($renderCallback);
    }
}
