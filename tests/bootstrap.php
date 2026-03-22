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

    // Load scoped dependencies autoloader (wp-scoper output).
    // Must come after ABSPATH is defined since packages/autoload.php guards on it.
    $scopedAutoloader = dirname(__DIR__) . '/packages/autoload.php';
    if (file_exists($scopedAutoloader)) {
        require_once $scopedAutoloader;
    }

    // Stub WordPress functions used by unit-tested classes.
    if (!function_exists('get_option')) {
        function get_option(string $option, $default = false) {
            return $GLOBALS['_test_options'][$option] ?? $default;
        }
    }

    if (!function_exists('get_userdata')) {
        function get_userdata(int $userId) {
            // Allow tests to override via $GLOBALS['_test_userdata'].
            return $GLOBALS['_test_userdata'] ?? false;
        }
    }

    if (!function_exists('cache_users')) {
        function cache_users(array $userIds): void {
            // No-op in tests — get_userdata is already faked.
        }
    }

    $GLOBALS['_test_user_meta'] = [];

    if (!function_exists('get_user_meta')) {
        function get_user_meta(int $userId, string $key = '', bool $single = false) {
            if ($key === '') {
                return $GLOBALS['_test_user_meta'][$userId] ?? [];
            }
            $value = $GLOBALS['_test_user_meta'][$userId][$key] ?? null;
            if ($value === null) {
                return $single ? '' : [];
            }
            return $single ? $value : [$value];
        }
    }

    if (!function_exists('update_user_meta')) {
        function update_user_meta(int $userId, string $key, $value, $prevValue = '') {
            $GLOBALS['_test_user_meta'][$userId][$key] = $value;
            return true;
        }
    }

    if (!function_exists('delete_user_meta')) {
        function delete_user_meta(int $userId, string $key, $value = '') {
            unset($GLOBALS['_test_user_meta'][$userId][$key]);
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
                'name'        => 'Test Site',
                'url'         => 'http://localhost',
                'description' => 'Just a test site',
                default       => '',
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
            $GLOBALS['_test_do_action_calls'][] = ['hook' => $hookName, 'args' => $args];
        }
    }

    if (!function_exists('sanitize_text_field')) {
        function sanitize_text_field($str) {
            return trim(strip_tags((string) $str));
        }
    }

    if (!function_exists('sanitize_textarea_field')) {
        function sanitize_textarea_field($str) {
            return trim(strip_tags((string) $str));
        }
    }

    if (!function_exists('wp_parse_args')) {
        function wp_parse_args($args, $defaults = []) {
            if (is_object($args)) {
                $parsed = get_object_vars($args);
            } elseif (is_array($args)) {
                $parsed = &$args;
            } else {
                parse_str($args, $parsed);
            }
            return array_merge($defaults, $parsed);
        }
    }

    if (!function_exists('esc_textarea')) {
        function esc_textarea(string $text): string {
            return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
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
                'mysql'     => gmdate('Y-m-d H:i:s'),
                'timestamp' => time(),
                default     => gmdate($type),
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

    if (!function_exists('esc_attr')) {
        function esc_attr(string $text) {
            return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }
    }

    if (!function_exists('esc_html_e')) {
        function esc_html_e(string $text, string $domain = 'default'): void {
            echo esc_html($text);
        }
    }

    if (!function_exists('esc_html__')) {
        function esc_html__(string $text, string $domain = 'default'): string {
            return esc_html($text);
        }
    }

    if (!function_exists('esc_attr_e')) {
        function esc_attr_e(string $text, string $domain = 'default'): void {
            echo esc_attr($text);
        }
    }

    if (!function_exists('esc_attr__')) {
        function esc_attr__(string $text, string $domain = 'default'): string {
            return esc_attr($text);
        }
    }

    if (!function_exists('date_i18n')) {
        function date_i18n(string $format, $timestamp = false, bool $gmt = false): string {
            if ($timestamp === false) {
                $timestamp = time();
            }
            return date($format, $timestamp);
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
            if (isset($GLOBALS['_test_actions'])) {
                $GLOBALS['_test_actions'][$hookName][] = $callback;
            }
        }
    }

    if (!function_exists('has_action')) {
        function has_action(string $hookName, $callback = false) {
            return $GLOBALS['_test_has_action'][$hookName] ?? false;
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
            $result = $GLOBALS['_test_get_users_result'] ?? [];
            $deleted = $GLOBALS['_test_deleted_users'] ?? [];

            if (!empty($deleted)) {
                $result = array_filter($result, fn($u) => !in_array($u->ID, $deleted, true));
            }

            return array_values($result);
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

    if (!function_exists('wp_insert_user')) {
        function wp_insert_user($userdata) {
            $GLOBALS['_test_wp_insert_user_data'] = $userdata;
            return $GLOBALS['_test_wp_insert_user_result'] ?? 1;
        }
    }

    if (!function_exists('wp_update_user')) {
        function wp_update_user($userdata) {
            return $GLOBALS['_test_wp_update_user_result'] ?? ($userdata['ID'] ?? 1);
        }
    }

    if (!function_exists('wp_set_password')) {
        function wp_set_password(string $password, int $userId): void {
            // No-op in tests.
        }
    }

    if (!function_exists('wp_check_password')) {
        function wp_check_password(string $password, string $hash, $userId = ''): bool {
            return $GLOBALS['_test_wp_check_password_result'] ?? false;
        }
    }

    if (!function_exists('wp_logout')) {
        function wp_logout(): void {
            $GLOBALS['_test_current_user_id'] = 0;
        }
    }

    if (!function_exists('is_email')) {
        function is_email(string $email) {
            return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : false;
        }
    }

    if (!function_exists('sanitize_email')) {
        function sanitize_email(string $email): string {
            return filter_var($email, FILTER_SANITIZE_EMAIL) ?: '';
        }
    }

    if (!function_exists('sanitize_user')) {
        function sanitize_user(string $username, bool $strict = false): string {
            return trim($username);
        }
    }

    if (!function_exists('home_url')) {
        function home_url(string $path = '', ?string $scheme = null): string {
            return 'http://localhost' . ($path ? '/' . ltrim($path, '/') : '');
        }
    }

    if (!function_exists('current_user_can')) {
        function current_user_can(string $capability, ...$args): bool {
            return $GLOBALS['_test_current_user_can'] ?? false;
        }
    }

    if (!function_exists('rest_url')) {
        function rest_url(string $path = ''): string {
            return 'http://localhost/wp-json/' . ltrim($path, '/');
        }
    }

    if (!function_exists('wp_create_nonce')) {
        function wp_create_nonce(string $action = '-1'): string {
            return 'test-nonce-' . $action;
        }
    }

    if (!function_exists('update_option')) {
        function update_option(string $option, $value, $autoload = null): bool {
            $GLOBALS['_test_options'][$option] = $value;
            return true;
        }
    }

    if (!function_exists('add_option')) {
        function add_option(string $option, $value = '', string $deprecated = '', $autoload = 'yes'): bool {
            $GLOBALS['_test_options'][$option] = $value;
            return true;
        }
    }

    if (!function_exists('delete_option')) {
        function delete_option(string $option): bool {
            unset($GLOBALS['_test_options'][$option]);
            return true;
        }
    }

    if (!function_exists('add_rewrite_rule')) {
        function add_rewrite_rule(string $regex, string $query, string $after = 'bottom'): void {
            // No-op in tests.
        }
    }

    if (!function_exists('flush_rewrite_rules')) {
        function flush_rewrite_rules(bool $hard = true): void {
            // No-op in tests.
        }
    }

    if (!function_exists('get_query_var')) {
        function get_query_var(string $var, $default = '') {
            return $GLOBALS['_test_query_vars'][$var] ?? $default;
        }
    }

    if (!function_exists('add_query_arg')) {
        function add_query_arg(...$args) {
            if (count($args) === 3) {
                return $args[2] . '?' . $args[0] . '=' . $args[1];
            }
            return '';
        }
    }

    if (!function_exists('wp_enqueue_script')) {
        function wp_enqueue_script(string $handle, string $src = '', array $deps = [], $ver = false, $args = false): void {
            // No-op in tests.
        }
    }

    if (!function_exists('wp_localize_script')) {
        function wp_localize_script(string $handle, string $objectName, array $l10n): bool {
            return true;
        }
    }

    if (!function_exists('wp_enqueue_style')) {
        function wp_enqueue_style(string $handle, string $src = '', array $deps = [], $ver = false, string $media = 'all'): void {
            // No-op in tests.
        }
    }

    if (!function_exists('wp_redirect')) {
        function wp_redirect(string $location, int $status = 302, string $xRedirectBy = 'WordPress'): bool {
            $GLOBALS['_test_redirect'] = ['location' => $location, 'status' => $status];
            return true;
        }
    }

    if (!function_exists('wp_safe_redirect')) {
        function wp_safe_redirect(string $location, int $status = 302, string $xRedirectBy = 'WordPress'): bool {
            $GLOBALS['_test_redirect'] = ['location' => $location, 'status' => $status];
            return true;
        }
    }

    if (!function_exists('wp_clear_auth_cookie')) {
        function wp_clear_auth_cookie(): void {
            $GLOBALS['_test_auth_cookie_cleared'] = true;
        }
    }

    if (!function_exists('wp_doing_ajax')) {
        function wp_doing_ajax(): bool {
            return $GLOBALS['_test_doing_ajax'] ?? false;
        }
    }

    if (!function_exists('get_current_screen')) {
        function get_current_screen() {
            return $GLOBALS['_test_current_screen'] ?? null;
        }
    }

    if (!function_exists('admin_url')) {
        function admin_url(string $path = ''): string {
            return 'http://localhost/wp-admin/' . ltrim($path, '/');
        }
    }

    if (!function_exists('show_admin_bar')) {
        function show_admin_bar(bool $show): void {
            // No-op in tests.
        }
    }

    if (!function_exists('wp_login_url')) {
        function wp_login_url(string $redirect = '', bool $forceReauth = false): string {
            return 'http://localhost/wp-login.php';
        }
    }

    if (!function_exists('wp_timezone')) {
        function wp_timezone(): \DateTimeZone {
            return new \DateTimeZone('UTC');
        }
    }

    if (!function_exists('wp_remote_post')) {
        function wp_remote_post(string $url, array $args = []) {
            $GLOBALS['_test_wp_remote_post_last_url'] = $url;
            $GLOBALS['_test_wp_remote_post_last_args'] = $args;
            return $GLOBALS['_test_wp_remote_post'] ?? new \WP_Error('not_configured', 'Test not configured');
        }
    }

    if (!function_exists('wp_remote_get')) {
        function wp_remote_get(string $url, array $args = []) {
            $mock = $GLOBALS['_test_wp_remote_get'] ?? new \WP_Error('not_configured', 'Test not configured');

            return is_callable($mock) ? $mock($url, $args) : $mock;
        }
    }

    if (!function_exists('wp_remote_retrieve_body')) {
        function wp_remote_retrieve_body($response): string {
            if (is_wp_error($response)) {
                return '';
            }
            return $response['body'] ?? '';
        }
    }

    if (!function_exists('wp_remote_retrieve_response_code')) {
        function wp_remote_retrieve_response_code($response): int {
            if (is_wp_error($response)) {
                return 0;
            }
            return $response['response']['code'] ?? 200;
        }
    }

    if (!function_exists('wp_remote_retrieve_header')) {
        function wp_remote_retrieve_header($response, string $header): string {
            if (is_wp_error($response)) {
                return '';
            }
            $header = strtolower($header);
            return $response['headers'][$header] ?? '';
        }
    }

    if (!function_exists('wp_tempnam')) {
        function wp_tempnam(string $prefix = ''): string {
            return tempnam(sys_get_temp_dir(), $prefix);
        }
    }

    if (!function_exists('wp_upload_dir')) {
        function wp_upload_dir(): array {
            $dir = $GLOBALS['_test_upload_dir'] ?? sys_get_temp_dir() . '/wp-uploads';
            return [
                'basedir' => $dir,
                'baseurl' => 'http://localhost/wp-content/uploads',
            ];
        }
    }

    if (!function_exists('wp_mkdir_p')) {
        function wp_mkdir_p(string $target): bool {
            if (isset($GLOBALS['_test_wp_mkdir_p'])) {
                return $GLOBALS['_test_wp_mkdir_p'];
            }
            if (is_dir($target)) {
                return true;
            }
            return @mkdir($target, 0755, true);
        }
    }

    if (!function_exists('wp_get_image_editor')) {
        function wp_get_image_editor(string $path, array $args = []) {
            if (isset($GLOBALS['_test_image_editor'])) {
                return $GLOBALS['_test_image_editor'];
            }
            return new \WP_Error('no_editor', 'No image editor in test environment');
        }
    }

    if (!function_exists('esc_url_raw')) {
        function esc_url_raw(string $url, ?array $protocols = null): string {
            return filter_var($url, FILTER_SANITIZE_URL) ?: '';
        }
    }

    if (!function_exists('wp_add_inline_script')) {
        function wp_add_inline_script(string $handle, string $data, string $position = 'after'): bool {
            return true;
        }
    }

    if (!function_exists('sanitize_title')) {
        function sanitize_title(string $title, string $fallback = '', string $context = 'save'): string {
            $title = strtolower(trim($title));
            $title = preg_replace('/[^a-z0-9\-]/', '-', $title);
            $title = preg_replace('/-+/', '-', $title);
            return trim($title, '-') ?: $fallback;
        }
    }

    if (!function_exists('wp_generate_password')) {
        function wp_generate_password(int $length = 12, bool $specialChars = true, bool $extraSpecialChars = false): string {
            $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            $password = '';
            for ($i = 0; $i < $length; $i++) {
                $password .= $chars[random_int(0, strlen($chars) - 1)];
            }
            return $password;
        }
    }

    if (!function_exists('sanitize_file_name')) {
        function sanitize_file_name(string $filename): string {
            return preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
        }
    }

    if (!function_exists('absint')) {
        function absint($maybeint): int {
            return abs((int) $maybeint);
        }
    }

    if (!function_exists('plugin_dir_url')) {
        function plugin_dir_url(string $file): string {
            return 'http://localhost/wp-content/plugins/wp-sms/';
        }
    }

    if (!function_exists('remove_action')) {
        function remove_action(string $hookName, $callback, int $priority = 10): bool {
            return true;
        }
    }

    if (!function_exists('add_filter')) {
        function add_filter(string $hookName, $callback, int $priority = 10, int $acceptedArgs = 1) {
            // No-op in tests.
        }
    }

    if (!function_exists('apply_filters')) {
        function apply_filters(string $hookName, $value, ...$args) {
            $callback = $GLOBALS['_test_apply_filters'][$hookName] ?? null;

            if ($callback !== null) {
                return $callback($value, ...$args);
            }

            return $value;
        }
    }

    if (!function_exists('wp_hash_password')) {
        function wp_hash_password(string $password): string {
            return password_hash($password, PASSWORD_DEFAULT);
        }
    }

    if (!function_exists('get_permalink')) {
        function get_permalink($post = 0) {
            $id = is_object($post) ? $post->ID : (int) $post;
            return $GLOBALS['_test_permalink'] ?? 'http://localhost/?p=' . $id;
        }
    }

    if (!function_exists('get_comment')) {
        function get_comment($comment = null, string $output = 'OBJECT') {
            return $GLOBALS['_test_comment'] ?? null;
        }
    }

    if (!function_exists('wp_insert_post')) {
        function wp_insert_post($postarr, bool $wpError = false) {
            $GLOBALS['_test_wp_insert_post_data'] = $postarr;
            $result = $GLOBALS['_test_wp_insert_post_result'] ?? 1;
            if ($wpError && $result === 0) {
                return new \WP_Error('insert_failed', 'Could not insert post');
            }
            return $result;
        }
    }

    if (!function_exists('wp_delete_user')) {
        function wp_delete_user(int $userId, ?int $reassign = null): bool {
            $GLOBALS['_test_deleted_users'][] = $userId;
            unset($GLOBALS['_test_user_meta'][$userId]);
            return true;
        }
    }

    if (!function_exists('__')) {
        function __(string $text, string $domain = 'default'): string {
            return $text;
        }
    }

    if (!function_exists('is_ssl')) {
        function is_ssl(): bool {
            return $GLOBALS['_test_is_ssl'] ?? false;
        }
    }

    if (!function_exists('wp_destroy_other_sessions')) {
        function wp_destroy_other_sessions(): void {
            // No-op in tests.
        }
    }

    if (!defined('ARRAY_A')) {
        define('ARRAY_A', 'ARRAY_A');
    }

    if (!defined('OBJECT')) {
        define('OBJECT', 'OBJECT');
    }

    if (!defined('AUTH_KEY')) {
        define('AUTH_KEY', 'test-auth-key-for-unit-tests');
    }

    if (!defined('DAY_IN_SECONDS')) {
        define('DAY_IN_SECONDS', 86400);
    }

    if (!defined('WEEK_IN_SECONDS')) {
        define('WEEK_IN_SECONDS', 604800);
    }

    // Multisite stubs.

    if (!function_exists('is_multisite')) {
        function is_multisite(): bool {
            return $GLOBALS['_test_is_multisite'] ?? false;
        }
    }

    if (!function_exists('get_sites')) {
        function get_sites(array $args = []): array {
            return $GLOBALS['_test_sites'] ?? [];
        }
    }

    if (!function_exists('switch_to_blog')) {
        function switch_to_blog(int $blogId): bool {
            $GLOBALS['_test_switched_blog_calls'][] = $blogId;
            return true;
        }
    }

    if (!function_exists('restore_current_blog')) {
        function restore_current_blog(): bool {
            $GLOBALS['_test_restore_blog_calls'] = ($GLOBALS['_test_restore_blog_calls'] ?? 0) + 1;
            return true;
        }
    }

    if (!function_exists('get_site_option')) {
        function get_site_option(string $option, $default = false) {
            if ($option === 'active_sitewide_plugins') {
                return $GLOBALS['_test_active_sitewide_plugins'] ?? $default;
            }
            return $GLOBALS['_test_site_options'][$option] ?? $default;
        }
    }

    if (!function_exists('plugin_basename')) {
        function plugin_basename(string $file): string {
            // Return a consistent basename for testing.
            return 'wp-sms/wp-sms.php';
        }
    }

    if (!defined('WP_SMS_DIR')) {
        define('WP_SMS_DIR', dirname(__DIR__) . '/');
    }

    if (!defined('WP_SMS_URL')) {
        define('WP_SMS_URL', 'http://localhost/wp-content/plugins/wp-sms/');
    }

    if (!defined('WP_SMS_VERSION')) {
        define('WP_SMS_VERSION', '8.0');
    }

    if (!defined('WP_SMS_MAIN_FILE')) {
        define('WP_SMS_MAIN_FILE', dirname(__DIR__) . '/wp-sms.php');
    }

    // Action Scheduler stubs.

    if (!function_exists('as_has_scheduled_action')) {
        function as_has_scheduled_action(string $hook, array $args = [], string $group = ''): bool {
            foreach ($GLOBALS['_test_as_scheduled_actions'] ?? [] as $action) {
                if ($action['hook'] === $hook && $action['args'] === $args && $action['group'] === $group) {
                    return true;
                }
            }
            return false;
        }
    }

    if (!function_exists('as_schedule_recurring_action')) {
        function as_schedule_recurring_action(int $timestamp, int $interval, string $hook, array $args = [], string $group = ''): int {
            $GLOBALS['_test_as_scheduled_actions'][] = [
                'timestamp' => $timestamp,
                'interval'  => $interval,
                'hook'      => $hook,
                'args'      => $args,
                'group'     => $group,
            ];
            return count($GLOBALS['_test_as_scheduled_actions']);
        }
    }

    if (!function_exists('as_unschedule_all_actions')) {
        function as_unschedule_all_actions(string $hook, array $args = [], string $group = ''): void {
            $GLOBALS['_test_as_scheduled_actions'] = array_values(array_filter(
                $GLOBALS['_test_as_scheduled_actions'] ?? [],
                fn($a) => !($a['hook'] === $hook && $a['args'] === $args && $a['group'] === $group),
            ));
        }
    }

    // Initialize test globals.
    $GLOBALS['_test_options'] = [];
    $GLOBALS['_test_query_vars'] = [];
    $GLOBALS['_test_do_action_calls'] = [];
    $GLOBALS['_test_apply_filters'] = [];
    $GLOBALS['_test_switched_blog_calls'] = [];
    $GLOBALS['_test_restore_blog_calls'] = 0;
    $GLOBALS['_test_as_scheduled_actions'] = [];

}

// WPCF7_ContactForm stub for CF7 integration tests.
if (!class_exists('WPCF7_ContactForm')) {
    class WPCF7_ContactForm {
        private array $properties = [];

        public function prop(string $name) {
            return $this->properties[$name] ?? null;
        }

        public function set_properties(array $properties): void {
            $this->properties = array_merge($this->properties, $properties);
        }

        public function suggest_mail_tags(string $name = ''): string {
            return '';
        }
    }
}

// WP_User stub.
if (!class_exists('WP_User')) {
    class WP_User {
        public int $ID = 0;
        public string $user_login = '';
        public string $user_email = '';
        public string $user_pass = '';
        public string $display_name = '';
        public string $first_name = '';
        public string $last_name = '';
        public string $user_registered = '';
        public array $roles = [];

        public function __construct(int $id = 0) {
            $this->ID = $id;
        }
    }
}

// WP_User_Query stub.
if (!class_exists('WP_User_Query')) {
    class WP_User_Query {
        private int $total = 0;

        public function __construct(array $args = []) {
            $this->total = $GLOBALS['_test_user_query_total'] ?? 0;
        }

        public function get_total(): int {
            return $this->total;
        }
    }
}

// WP_Error stub (must be at top level, not inside if-block).
if (!class_exists('WP_Error')) {
    class WP_Error {
        private string $code;
        private string $message;
        private $data;
        private array $errors = [];

        public function __construct(string $code = '', string $message = '', $data = '') {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
            if ($code !== '') {
                $this->errors[$code] = [$message];
            }
        }

        public function add(string $code, string $message, $data = ''): void {
            if ($this->code === '') {
                $this->code = $code;
                $this->message = $message;
                $this->data = $data;
            }
            $this->errors[$code][] = $message;
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

        public function get_error_codes(): array {
            return array_keys($this->errors);
        }

        public function has_errors(): bool {
            return !empty($this->errors);
        }
    }
}

// WP_REST_Request stub.
if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request {
        private array $params = [];
        private array $headers = [];
        private ?string $body = null;
        private string $route = '';
        private array $fileParams = [];

        public function __construct(string $method = 'GET', string $route = '') {
            $this->route = $route;
        }

        public function get_route(): string {
            return $this->route;
        }

        public function set_route(string $route): void {
            $this->route = $route;
        }

        public function set_param(string $key, $value): void {
            $this->params[$key] = $value;
        }

        public function get_param(string $key) {
            return $this->params[$key] ?? null;
        }

        public function get_params(): array {
            return $this->params;
        }

        public function set_header(string $key, string $value): void {
            $this->headers[strtolower($key)] = $value;
        }

        public function get_header(string $key): ?string {
            return $this->headers[strtolower($key)] ?? null;
        }

        public function set_body(string $body): void {
            $this->body = $body;
        }

        public function get_body(): ?string {
            return $this->body;
        }

        public function get_json_params(): array {
            if ($this->body === null) {
                return [];
            }
            return json_decode($this->body, true) ?? [];
        }

        public function set_file_params(array $files): void {
            $this->fileParams = $files;
        }

        public function get_file_params(): array {
            return $this->fileParams;
        }
    }
}

// Load scoped packages for WSms\Dependencies\* classes.
$packagesAutoload = dirname(__DIR__) . '/packages/autoload.php';
if (file_exists($packagesAutoload)) {
    require_once $packagesAutoload;
}

// WP_Site stub for multisite tests.
if (!class_exists('WP_Site')) {
    class WP_Site {
        public $blog_id = '1';
        public $domain = 'localhost';
        public $path = '/';
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

// WP_Session_Tokens stub for unit tests.
if (!class_exists('WP_Session_Tokens')) {
    class WP_Session_Tokens {
        private static array $instances = [];
        private int $userId;

        protected function __construct(int $userId) {
            $this->userId = $userId;
        }

        public static function get_instance(int $userId): self {
            if (!isset(self::$instances[$userId])) {
                self::$instances[$userId] = new self($userId);
            }
            return self::$instances[$userId];
        }

        public function destroy_all(): void {
            $GLOBALS['_test_sessions_destroyed'][$this->userId] = true;
        }

        public static function resetInstances(): void {
            self::$instances = [];
        }
    }
}

// WPForms_Field stub for unit tests.
if (!class_exists('WPForms_Field')) {
    class WPForms_Field {
        public $name = '';
        public $type = '';
        public $icon = '';
        public $order = 0;
        public $group = 'standard';
        public $keywords = '';

        public function __construct() {
            $this->init();
        }

        public function init() {}

        public function validate($field_id, $field_submit, $form_data) {
            if (!empty($form_data['fields'][$field_id]['required']) && empty($field_submit)) {
                wpforms()->obj('process')->errors[$form_data['id']][$field_id] = 'This field is required.';
            }
        }

        public function field_option($option, $field, $args = []) {}
        public function field_preview_option($option, $field, $args = []) {}
        public function field_display_error($key, $field) {}
    }
}

// WPForms process stub for tests.
if (!class_exists('WPFormsProcess')) {
    class WPFormsProcess {
        public array $errors = [];
        public array $fields = [];
    }
}

// WPForms application stub for tests.
if (!class_exists('WPFormsApp')) {
    class WPFormsApp {
        private WPFormsProcess $process;

        public function __construct() {
            $this->process = new WPFormsProcess();
        }

        public function obj(string $name) {
            if ($name === 'process') {
                return $this->process;
            }
            return null;
        }
    }
}

if (!function_exists('wpforms')) {
    function wpforms() {
        static $instance = null;
        if ($instance === null) {
            $instance = new WPFormsApp();
        }
        return $instance;
    }
}

// WooCommerce stubs for unit tests.
if (!function_exists('wc_get_order')) {
    function wc_get_order($orderId) {
        return $GLOBALS['_test_wc_order'] ?? null;
    }
}

if (!function_exists('is_checkout')) {
    function is_checkout(): bool {
        return $GLOBALS['_test_is_checkout'] ?? false;
    }
}

if (!function_exists('is_account_page')) {
    function is_account_page(): bool {
        return $GLOBALS['_test_is_account_page'] ?? false;
    }
}

if (!function_exists('wc_add_notice')) {
    function wc_add_notice(string $message, string $type = 'success', $data = []): void {
        $GLOBALS['_test_wc_notices'][] = ['message' => $message, 'type' => $type];
    }
}

if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user() {
        return $GLOBALS['_test_userdata'] ?? new \WP_User(0);
    }
}

// WC_Order stub for tests.
if (!class_exists('WC_Order_Stub')) {
    class WC_Order_Stub {
        private int $id;
        private array $meta = [];
        private string $billingEmail = '';
        private string $billingPhone = '';
        private string $billingFirstName = '';
        private string $billingLastName = '';
        private string $total = '0.00';
        private string $status = 'pending';
        private string $currency = 'USD';
        private string $paymentMethodTitle = 'Credit Card';
        private ?object $dateCreated = null;
        private array $items = [];
        private array $notes = [];

        public function __construct(int $id = 1) {
            $this->id = $id;
        }

        public function get_id(): int {
            return $this->id;
        }

        public function get_billing_email(): string {
            return $this->billingEmail;
        }

        public function set_billing_email(string $email): void {
            $this->billingEmail = $email;
        }

        public function get_billing_phone(): string {
            return $this->billingPhone;
        }

        public function set_billing_phone(string $phone): void {
            $this->billingPhone = $phone;
        }

        public function get_billing_first_name(): string {
            return $this->billingFirstName;
        }

        public function set_billing_first_name(string $name): void {
            $this->billingFirstName = $name;
        }

        public function get_billing_last_name(): string {
            return $this->billingLastName;
        }

        public function set_billing_last_name(string $name): void {
            $this->billingLastName = $name;
        }

        public function get_total(): string {
            return $this->total;
        }

        public function set_total(string $total): void {
            $this->total = $total;
        }

        public function get_status(): string {
            return $this->status;
        }

        public function update_status(string $status, string $note = ''): void {
            $this->status = $status;
            if ($note) {
                $this->notes[] = ['note' => $note, 'is_customer_note' => false];
            }
        }

        public function get_currency(): string {
            return $this->currency;
        }

        public function set_currency(string $currency): void {
            $this->currency = $currency;
        }

        public function get_payment_method_title(): string {
            return $this->paymentMethodTitle;
        }

        public function set_payment_method_title(string $title): void {
            $this->paymentMethodTitle = $title;
        }

        public function get_date_created(): ?object {
            return $this->dateCreated;
        }

        public function set_date_created(?object $date): void {
            $this->dateCreated = $date;
        }

        public function get_item_count(): int {
            return count($this->items);
        }

        public function get_items(): array {
            return $this->items;
        }

        public function set_items(array $items): void {
            $this->items = $items;
        }

        public function add_order_note(string $note, bool $isCustomerNote = false): int {
            $noteId = count($this->notes) + 1;
            $this->notes[] = ['note' => $note, 'is_customer_note' => $isCustomerNote, 'id' => $noteId];
            return $noteId;
        }

        public function get_notes(): array {
            return $this->notes;
        }

        public function update_meta_data(string $key, $value): void {
            $this->meta[$key] = $value;
        }

        public function get_meta(string $key) {
            return $this->meta[$key] ?? '';
        }

        public function save(): void {
            // No-op in tests.
        }
    }
}

// WC_Order_Item_Product stub for tests.
if (!class_exists('WC_Order_Item_Stub')) {
    class WC_Order_Item_Stub {
        private int $productId;
        private string $name;
        private int $quantity;
        private string $total = '0.00';

        public function __construct(int $productId, string $name, int $quantity = 1) {
            $this->productId = $productId;
            $this->name = $name;
            $this->quantity = $quantity;
        }

        public function get_product_id(): int {
            return $this->productId;
        }

        public function get_name(): string {
            return $this->name;
        }

        public function get_quantity(): int {
            return $this->quantity;
        }

        public function get_total(): string {
            return $this->total;
        }

        public function set_total(string $total): void {
            $this->total = $total;
        }
    }
}

// WooCommerce RouteException stub for block checkout validation tests.
if (!class_exists('Automattic\WooCommerce\StoreApi\Exceptions\RouteException')) {
    eval('namespace Automattic\WooCommerce\StoreApi\Exceptions {
        class RouteException extends \Exception {
            public function __construct(string $errorCode = "", string $message = "", int $httpStatus = 400) {
                parent::__construct($message, $httpStatus);
            }
        }
    }');
}
