<?php

namespace WP_SMS;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

class Shortcode
{

    public function __construct()
    {

        // Add the shortcode [wp-sms-subscriber-form]
        add_shortcode('wp-sms-subscriber-form', array($this, 'register_shortcode'));
    }

    /**
     * Shortcode plugin
     *
     * @param $atts
     *
     * @return false|string
     * @internal param param $Not
     */
    public function register_shortcode($atts)
    {
        ob_start();
        Newsletter::loadNewsLetter();

        return ob_get_clean();
    }
}

new Shortcode();