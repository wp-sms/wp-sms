<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package WP_SMS
 */

// Enable WP_DEBUG to make the test gateway available
if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}

// Locate the WordPress testing library directory.
$_tests_dir     = getenv('WP_TESTS_DIR') ?: rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
$_wordpress_dir = getenv('WP_TESTS_DIR') ?: rtrim(sys_get_temp_dir(), '/\\') . '/wordpress';

// Ensure the testing library exists.
if (!file_exists("{$_tests_dir}/includes/functions.php")) {
    echo "Could not find {$_tests_dir}/includes/functions.php. Have you run bin/install-wp-tests.sh?" . PHP_EOL;
    exit(1);
}

// Load WordPress PHPUnit Polyfills configuration if available.
if (false !== ($phpunit_polyfills_path = getenv('WP_TESTS_PHPUNIT_POLYFILLS_PATH'))) {
    define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', $phpunit_polyfills_path);
}

// Give access to the `tests_add_filter()` function.
require_once "{$_tests_dir}/includes/functions.php";

// Autoload dependencies (e.g., Faker).
require_once dirname(__DIR__) . '/vendor/autoload.php';

/**
 * Load the plugins being tested.
 */
function _manually_load_plugins()
{
    // Table creation on test environment.
    $network_wide   = is_multisite();

    // Load WooCommerce from the actual WordPress installation
    $woocommerce_path = ABSPATH . 'wp-content/plugins/woocommerce/woocommerce.php';
    if (file_exists($woocommerce_path)) {
        require $woocommerce_path;
    }

    require dirname(__FILE__, 2) . '/wp-sms.php';

    WP_SMS::get_instance()->activate($network_wide);
}

// Hook to load the plugins.
tests_add_filter('muplugins_loaded', '_manually_load_plugins');

// Start up the WordPress testing environment.
require "{$_tests_dir}/includes/bootstrap.php";
