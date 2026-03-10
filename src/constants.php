<?php

defined('ABSPATH') || exit;

$pluginDir = dirname(__DIR__);

if (!defined('WP_SMS_VERSION')) {
    define('WP_SMS_VERSION', '8.0');
}

if (!defined('WP_SMS_URL')) {
    define('WP_SMS_URL', plugin_dir_url($pluginDir . '/wp-sms.php'));
}

if (!defined('WP_SMS_DIR')) {
    define('WP_SMS_DIR', $pluginDir . '/');
}

if (!defined('WP_SMS_MAIN_FILE')) {
    define('WP_SMS_MAIN_FILE', WP_SMS_DIR . 'wp-sms.php');
}

if (!defined('WP_SMS_SITE')) {
    define('WP_SMS_SITE', 'https://wsms.io');
}

unset($pluginDir);
