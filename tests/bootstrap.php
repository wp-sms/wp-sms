<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package WP_Statistics
 */

// Locate the WordPress testing library directory.
$_tests_dir = getenv('WP_TESTS_DIR') ?: rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';

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

// Load pluggable.php to enable WordPress functions like wp_get_current_user().
require_once dirname(__FILE__, 2) . '/wp-includes/pluggable.php'; // Update path to correct WordPress location

/**
 * Load the plugins being tested.
 */
function _manually_load_plugins()
{
    require dirname(__FILE__, 2) . '/wp-sms.php';
    // Table creation on test environment.
    $network_wide = is_multisite();
    WP_SMS::get_instance()->activate($network_wide);
    
    activate_plugin('woocommerce/woocommerce.php'); // Use relative path to WooCommerce plugin.
}

// Hook to load the plugins.
tests_add_filter('muplugins_loaded', '_manually_load_plugins');

// Start up the WordPress testing environment.
require "{$_tests_dir}/includes/bootstrap.php";

// Global Faker instance (optional, for shared use across tests).
global $faker;
$faker = \Faker\Factory::create();
