<?php

// Plugin-specific constants that PHPStan needs to know about.
define('WP_SMS_VERSION', '8.0');
define('WP_SMS_DIR', __DIR__ . '/');
define('WP_SMS_URL', 'https://example.com/wp-content/plugins/wp-sms/');
define('WP_SMS_MAIN_FILE', __DIR__ . '/wp-sms.php');

// Stubs for external classes that PHPStan can't resolve.
// These are scoped dependencies or optional plugin integrations.
if (!class_exists('WSms\\Dependencies\\Psr\\Log\\AbstractLogger')) {
    // @phpstan-ignore-next-line
    class_alias('stdClass', 'WSms\\Dependencies\\Psr\\Log\\AbstractLogger');
}

if (!class_exists('WPForms_Field')) {
    class WPForms_Field {}
}

if (!interface_exists('Automattic\\WooCommerce\\Blocks\\Integrations\\IntegrationInterface')) {
    interface IntegrationInterface {}
    class_alias('IntegrationInterface', 'Automattic\\WooCommerce\\Blocks\\Integrations\\IntegrationInterface');
}
