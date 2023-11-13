<?php

namespace WP_SMS;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

/**
 * Class Privacy
 */
class Privacy
{
    /*
      * Render Privacy Page
      */
    public function render_page()
    {
        echo Helper::loadTemplate('admin/privacy-page.php', array(
            'title' => __('Privacy', 'wp-sms'),
        ));
    }
}

new Privacy();
