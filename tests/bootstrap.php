<?php
/**
 * PHPUnit bootstrap for WSMS.
 *
 * Loads Composer autoloader and WordPress test library.
 */

// Load Composer autoloader.
$autoloader = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
}

// Load WordPress test library if available.
$wpTestsDir = getenv('WP_TESTS_DIR');

if (!$wpTestsDir) {
    $wpTestsDir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

if (file_exists($wpTestsDir . '/includes/functions.php')) {
    // Give access to tests_add_filter() function.
    require_once $wpTestsDir . '/includes/functions.php';

    // Load the plugin.
    tests_add_filter('muplugins_loaded', function () {
        require dirname(__DIR__) . '/wp-sms.php';
    });

    // Start up the WP testing environment.
    require $wpTestsDir . '/includes/bootstrap.php';
} else {
    // Standalone mode — just define ABSPATH so guarded files can load.
    if (!defined('ABSPATH')) {
        define('ABSPATH', '/');
    }

    // Stub WordPress functions used by unit-tested classes.
    if (!function_exists('get_option')) {
        function get_option(string $option, $default = false) {
            return $default;
        }
    }

    if (!function_exists('get_userdata')) {
        function get_userdata(int $userId) {
            // Allow tests to override via $GLOBALS['_test_userdata'].
            return $GLOBALS['_test_userdata'] ?? false;
        }
    }

    if (!function_exists('get_user_meta')) {
        function get_user_meta(int $userId, string $key = '', bool $single = false) {
            return $single ? '' : [];
        }
    }

    if (!function_exists('update_user_meta')) {
        function update_user_meta(int $userId, string $key, $value, $prevValue = '') {
            return true;
        }
    }

    if (!function_exists('delete_user_meta')) {
        function delete_user_meta(int $userId, string $key, $value = '') {
            return true;
        }
    }

    if (!function_exists('wp_mail')) {
        function wp_mail($to, $subject, $message, $headers = '', $attachments = []) {
            return true;
        }
    }

    if (!function_exists('get_bloginfo')) {
        function get_bloginfo(string $show = '', string $filter = 'raw') {
            return match ($show) {
                'name' => 'Test Site',
                'url'  => 'http://localhost',
                default => '',
            };
        }
    }

    if (!function_exists('get_site_url')) {
        function get_site_url($blogId = null, string $path = '', ?string $scheme = null) {
            return 'http://localhost' . ($path ? '/' . ltrim($path, '/') : '');
        }
    }

    if (!function_exists('do_action')) {
        function do_action(string $hookName, ...$args) {
            // No-op in tests.
        }
    }

    if (!function_exists('sanitize_text_field')) {
        function sanitize_text_field($str) {
            return trim(strip_tags((string) $str));
        }
    }

    if (!function_exists('wp_unslash')) {
        function wp_unslash($value) {
            return is_string($value) ? stripslashes($value) : $value;
        }
    }

    if (!function_exists('current_time')) {
        function current_time(string $type, bool $gmt = false) {
            return match ($type) {
                'mysql' => gmdate('Y-m-d H:i:s'),
                'timestamp' => time(),
                default => time(),
            };
        }
    }

    if (!function_exists('wp_json_encode')) {
        function wp_json_encode($data, int $options = 0, int $depth = 512) {
            return json_encode($data, $options, $depth);
        }
    }

    if (!function_exists('esc_url')) {
        function esc_url(string $url, ?array $protocols = null, string $context = 'display') {
            return filter_var($url, FILTER_SANITIZE_URL) ?: '';
        }
    }

    if (!function_exists('esc_html')) {
        function esc_html(string $text) {
            return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }
    }

    // In-memory transient storage for tests.
    $GLOBALS['_test_transients'] = [];

    if (!function_exists('set_transient')) {
        function set_transient(string $key, $value, int $expiration = 0): bool {
            $GLOBALS['_test_transients'][$key] = [
                'value'   => $value,
                'expires' => $expiration > 0 ? time() + $expiration : 0,
            ];
            return true;
        }
    }

    if (!function_exists('get_transient')) {
        function get_transient(string $key) {
            if (!isset($GLOBALS['_test_transients'][$key])) {
                return false;
            }
            $entry = $GLOBALS['_test_transients'][$key];
            if ($entry['expires'] > 0 && $entry['expires'] < time()) {
                unset($GLOBALS['_test_transients'][$key]);
                return false;
            }
            return $entry['value'];
        }
    }

    if (!function_exists('delete_transient')) {
        function delete_transient(string $key): bool {
            unset($GLOBALS['_test_transients'][$key]);
            return true;
        }
    }

    if (!function_exists('add_action')) {
        function add_action(string $hookName, $callback, int $priority = 10, int $acceptedArgs = 1) {
            // No-op in tests.
        }
    }

    if (!function_exists('is_wp_error')) {
        function is_wp_error($thing): bool {
            return $thing instanceof \WP_Error;
        }
    }

    if (!function_exists('wp_authenticate')) {
        function wp_authenticate(string $username, string $password) {
            return $GLOBALS['_test_wp_authenticate_result'] ?? new \WP_Error('invalid', 'Invalid');
        }
    }

    if (!function_exists('wp_set_auth_cookie')) {
        function wp_set_auth_cookie(int $userId, bool $remember = false): void {
            // No-op in tests.
        }
    }

    if (!function_exists('wp_set_current_user')) {
        function wp_set_current_user(int $userId) {
            $GLOBALS['_test_current_user_id'] = $userId;
        }
    }

    if (!function_exists('get_current_user_id')) {
        function get_current_user_id(): int {
            return $GLOBALS['_test_current_user_id'] ?? 0;
        }
    }

    if (!function_exists('is_user_logged_in')) {
        function is_user_logged_in(): bool {
            return ($GLOBALS['_test_current_user_id'] ?? 0) > 0;
        }
    }

    if (!function_exists('get_users')) {
        function get_users(array $args = []): array {
            return $GLOBALS['_test_get_users_result'] ?? [];
        }
    }

    if (!function_exists('get_user_by')) {
        function get_user_by(string $field, $value) {
            return $GLOBALS['_test_get_user_by_result'] ?? false;
        }
    }

    if (!function_exists('__return_true')) {
        function __return_true(): bool {
            return true;
        }
    }

    if (!function_exists('register_rest_route')) {
        function register_rest_route(string $namespace, string $route, array $args = [], bool $override = false): bool {
            return true;
        }
    }

    if (!defined('AUTH_KEY')) {
        define('AUTH_KEY', 'test-auth-key-for-unit-tests');
    }

}

// WP_Error stub (must be at top level, not inside if-block).
if (!class_exists('WP_Error')) {
    class WP_Error {
        private string $code;
        private string $message;
        private $data;

        public function __construct(string $code = '', string $message = '', $data = '') {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
        }

        public function get_error_code(): string {
            return $this->code;
        }

        public function get_error_message(): string {
            return $this->message;
        }

        public function get_error_data() {
            return $this->data;
        }
    }
}

// WP_REST_Request stub.
if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request {
        private array $params = [];

        public function __construct(string $method = 'GET', string $route = '') {
        }

        public function set_param(string $key, $value): void {
            $this->params[$key] = $value;
        }

        public function get_param(string $key) {
            return $this->params[$key] ?? null;
        }
    }
}

// WP_REST_Response stub.
if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        public $data;
        public int $status;

        public function __construct($data = null, int $status = 200) {
            $this->data = $data;
            $this->status = $status;
        }

        public function get_data() {
            return $this->data;
        }

        public function get_status(): int {
            return $this->status;
        }
    }
}
