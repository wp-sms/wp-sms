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
    public $pagehook;

    public function render_page()
    {
        $args = [
            'title' => esc_html__('Privacy', 'wp-sms'),
        ];

        echo Helper::loadTemplate('admin/privacy-page.php', $args); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}

new Privacy();
