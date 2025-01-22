
<?php
/**
 * Plugin Name: WP SMS
 * Plugin URI: https://wp-sms-pro.com/
 * Description: Enhanced SMS & MMS Notifications, 2FA, OTP, and Integrations with WooCommerce, GravityForms, and More. Now with additional security and functionalities.
 * Version: 6.9.11
 * Author: VeronaLabs, Jaine Cassimiro
 * Author URI: https://veronalabs.com/
 * Text Domain: wp-sms
 * Domain Path: /languages
 * GitHub Plugin URI: https://github.com/veronalabs/wp-sms
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 4.1
 * Requires PHP: 7.0
 */

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

/**
 * Load Plugin Defines
 */
include_once __DIR__ . '/includes/defines.php';

define('WP_SMS_VERSION', '6.9.11');
define('WP_SMS_LOG_DIR', plugin_dir_path(__FILE__) . 'logs/'); // Directory for logs

/**
 * Ensure log directory exists
 */
if (!file_exists(WP_SMS_LOG_DIR)) {
    mkdir(WP_SMS_LOG_DIR, 0755, true);
}

/**
 * Load Plugin Functions
 */
require_once WP_SMS_DIR . 'includes/functions.php';
require_once WP_SMS_DIR . 'includes/class-wpsms-option.php';
require_once WP_SMS_DIR . 'includes/class-wpsms-gateway.php';
require WP_SMS_DIR . 'includes/class-wpsms.php';

/**
 * @return WP_SMS
 */
function WPSms()
{
    return WP_SMS::get_instance();
}

WPSms();

/**
 * Add security headers to prevent vulnerabilities.
 */
function wp_sms_add_security_headers() {
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
}
add_action('init', 'wp_sms_add_security_headers');

/**
 * Log SMS activity for debugging and auditing purposes.
 * @param string $message
 */
function wp_sms_log_activity($message) {
    $log_file = WP_SMS_LOG_DIR . 'sms-log-' . date('Y-m-d') . '.log';
    $log_message = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

/**
 * Test SMS sending functionality in admin panel.
 */
function wp_sms_test_sms_functionality() {
    if (is_admin() && current_user_can('manage_options')) {
        add_action('admin_menu', function () {
            add_submenu_page(
                'tools.php',
                'Test SMS',
                'Test SMS',
                'manage_options',
                'wp_sms_test',
                'wp_sms_test_page'
            );
        });
    }
}
add_action('init', 'wp_sms_test_sms_functionality');

function wp_sms_test_page() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wp_sms_test_number'])) {
        $number = sanitize_text_field($_POST['wp_sms_test_number']);
        $message = sanitize_text_field($_POST['wp_sms_test_message']);
        // Simulate sending SMS (Replace with actual logic)
        $status = WPSms()->send_sms($number, $message);
        if ($status) {
            echo '<div class="updated"><p>Test SMS sent successfully to ' . esc_html($number) . '</p></div>';
            wp_sms_log_activity("Test SMS sent to {$number}: {$message}");
        } else {
            echo '<div class="error"><p>Failed to send SMS to ' . esc_html($number) . '</p></div>';
        }
    }
    ?>
    <div class="wrap">
        <h1>Test SMS Functionality</h1>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th><label for="wp_sms_test_number">Phone Number</label></th>
                    <td><input name="wp_sms_test_number" id="wp_sms_test_number" type="text" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="wp_sms_test_message">Message</label></th>
                    <td><textarea name="wp_sms_test_message" id="wp_sms_test_message" class="regular-text"></textarea></td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" class="button-primary" value="Send Test SMS">
            </p>
        </form>
    </div>
    <?php
}

/**
 * Notify admin of SMS sending errors via email.
 * @param string $error_message
 */
function wp_sms_notify_error($error_message) {
    $admin_email = get_option('admin_email');
    wp_mail($admin_email, 'WP SMS Error Notification', $error_message);
    wp_sms_log_activity("Error notified to admin: {$error_message}");
}

/**
 * Hook into SMS sending errors to notify admin.
 */
add_action('wp_sms_error', 'wp_sms_notify_error');
?>
