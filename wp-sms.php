<?php
/**
 * Plugin Name: WSMS (formerly WP SMS)
 * Plugin URI: https://wsms.io/
 * Description: SMS & MMS Notifications, 2FA, OTP, and Integrations with E-Commerce and Form Builders
 * Version: 8.0
 * Author: VeronaLabs
 * Author URI: https://veronalabs.com/
 * Text Domain: wp-sms
 * Domain Path: /public/languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined('ABSPATH') || exit;

/*
|--------------------------------------------------------------------------
| Premium compatibility check
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/src/premium-compatibility.php';

if (wp_sms_is_premium_active()) {
    wp_sms_init_premium_compatibility(__FILE__);
    return;
}

/*
|--------------------------------------------------------------------------
| Autoloaders
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/compat/autoload.php';

// In production, wp-scoper generates packages/autoload.php.
// In development, Composer's vendor/autoload.php is used instead.
$composerAutoload = __DIR__ . '/packages/autoload.php';
if (!file_exists($composerAutoload)) {
    $composerAutoload = __DIR__ . '/vendor/autoload.php';
}
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

/*
|--------------------------------------------------------------------------
| Action Scheduler (shared library — must NOT be prefixed)
|--------------------------------------------------------------------------
*/
$actionSchedulerPath = __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php';
if (file_exists($actionSchedulerPath)) {
    require_once $actionSchedulerPath;
}

/*
|--------------------------------------------------------------------------
| Constants
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/src/constants.php';

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/src/functions.php';
require_once __DIR__ . '/compat/functions.php';

/*
|--------------------------------------------------------------------------
| Bootstrap
|--------------------------------------------------------------------------
*/
WSms\Bootstrap::init();
