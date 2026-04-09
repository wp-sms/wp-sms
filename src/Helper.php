<?php

namespace WP_SMS;

use WC_Blocks_Utils;
use WP_Error;
use WP_SMS\Components\NumberParser;
use WP_SMS\Utils\OptionUtil;
use WP_SMS\Utils\TimeZone;

if (!defined('ABSPATH')) exit;

/**
 * Class WP_SMS
 * @package WP_SMS
 * @description The helper that provides the useful methods for the plugin for development purposes.
 */
class Helper
{
    public static function getPluginAssetUrl($assetName, $plugin = 'wp-sms')
    {
        return plugins_url($plugin) . "/public/{$assetName}";
    }

    public static function getAssetPath($asset)
    {
        return plugin_dir_path(dirname(__FILE__, 1)) . $asset;
    }

    /**
     * @param $template
     * @param array $parameters
     * @param $isPro
     *
     * @return false|string|void
     */
    public static function loadTemplate($template, $parameters = [], $isPro = false)
    {
        $base_path = WP_SMS_DIR;

        if ($isPro) {
            $base_path = WP_SMS_PRO_DIR;
        }

        $templatePath = $base_path . "includes/templates/{$template}";

        if (file_exists($templatePath)) {
            ob_start();

            extract($parameters);
            require $templatePath;

            return ob_get_clean();
        }
    }

    /**
     * @return mixed|void|null
     */
    public static function getUserMobileFieldName()
    {
        $mobileFieldManager = new \WP_SMS\User\MobileFieldManager();
        return $mobileFieldManager->getHandler()->getUserMobileFieldName();
    }

    /**
     * @return string
     */
    public static function getWooCommerceCheckoutFieldName()
    {
        $mobileFieldHandler = (new \WP_SMS\User\MobileFieldManager())->getHandler();
        return $mobileFieldHandler instanceof \WP_SMS\User\MobileFieldHandler\WooCommerceAddMobileFieldHandler ?
            $mobileFieldHandler->getUserMobileFieldName() :
            'billing_phone';
    }

    /**
     * Get mobile field selector in the checkout page
     *
     * @return string
     */
    public static function getWooCommerceCheckoutMobileField()
    {
        if (self::isWooCheckoutBlock()) {
            // If the new checkout block is enabled

            if ("use_phone_field_in_wc_billing" === wp_sms_get_option('add_mobile_field')) {
                return '#billing-phone';
            }

            if ("add_mobile_field_in_wc_billing" === wp_sms_get_option('add_mobile_field')) {
                return '#billing-wpsms\\/mobile,#billing-wpsms-mobile';
            }
        } else {
            // If classic checkout mode is enabled

            $mobileFieldId = self::getWooCommerceCheckoutFieldName();
            if (substr($mobileFieldId, 0, 1) != '#') {
                $mobileFieldId = "#$mobileFieldId";
            }

            return $mobileFieldId;
        }
    }

    /**
     * Get submit button element selector in the checkout page
     *
     * @return string
     */
    public static function getWooCommerceCheckoutSubmitBtn()
    {
        if (self::isWooCheckoutBlock()) {
            // If the new checkout block is enabled
            return '.wc-block-components-checkout-place-order-button';
        } else {
            // If classic checkout mode is enabled
            return '#place_order';
        }
    }

    /**
     * Checks if the checkout page is using blocks.
     *
     * Dot't forget to use `is_checkout()` together with this method.
     *
     * @return bool
     */
    public static function isWooCheckoutBlock()
    {
        if (class_exists('WooCommerce')) {
            return WC_Blocks_Utils::has_block_in_page(wc_get_page_id('checkout'), 'woocommerce/checkout');
        }
    }

    /**
     * @param $userId
     *
     * @return mixed
     */
    public static function getUserMobileNumberByUserId($userId, $args = [])
    {
        $mobileFieldManager = new \WP_SMS\User\MobileFieldManager();
        return $mobileFieldManager->getHandler()->getMobileNumberByUserId($userId, $args);
    }

    /**
     * @param $number
     * @return \WP_User|null
     */
    public static function getUserByPhoneNumber($number)
    {
        if (empty($number)) {
            return null;
        }

        $users = get_users([
            'meta_key'     => self::getUserMobileFieldName(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            'meta_value'   => self::prepareMobileNumberQuery($number), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
            'meta_compare' => 'IN',
        ]);

        return !empty($users) ? array_values($users)[0] : null;
    }

    /**
     * @param $roleId
     * @param array $userIds
     *
     * @return array
     */
    public static function getUsersMobileNumbers($roleId = false, $userIds = array())
    {
        $mobileFieldKey = self::getUserMobileFieldName();

        $args = array(
            'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                array(
                    'key'     => $mobileFieldKey,
                    'value'   => '',
                    'compare' => '!=',
                ),
            ),
            'count_total' => false,
        );

        if ($roleId) {
            $args['role__in'] = $roleId;
        }

        // Add user IDs to include in the query
        if (count($userIds) > 0) {
            $args['include'] = $userIds;
        }

        $args  = apply_filters('wp_sms_mobile_numbers_query_args', $args);
        $users = get_users($args);

        $mobileNumbers = [];

        foreach ($users as $user) {
            if (isset($user->$mobileFieldKey)) {
                $mobileNumbers[] = $user->$mobileFieldKey;
            }
        }

        return array_unique($mobileNumbers);
    }

    /**
     * Get users mobile number count with role details
     *
     * @return array
     */
    public static function getUsersMobileNumberCountsWithRoleDetails()
    {
        $mobileFieldKey = self::getUserMobileFieldName();
        $all_roles      = wp_roles()->role_names;

        // Initialize the roles array with role details
        $roles = [];
        foreach ($all_roles as $role_key => $role_name) {
            $roles[$role_key] = [
                'name'  => $role_name,
                'count' => 0,
                // 'numbers' => []
            ];
        }

        $total_count = 0;

        $args = array(
            'meta_query' => array(
                array(
                    'key'     => $mobileFieldKey,
                    'value'   => '',
                    'compare' => '!=',
                ),
            ),
        );

        $args  = apply_filters('wp_sms_mobile_numbers_query_args', $args);
        $users = get_users($args);

        // $mobileNumbers = [];

        foreach ($users as $user) {
            if (isset($user->$mobileFieldKey)) {
                // $mobileNumbers[] = $user->$mobileFieldKey;
                $total_count++;
                foreach ($user->roles as $role) {
                    if (isset($roles[$role])) {
                        $roles[$role]['count']++;
                        // $roles[$role]['numbers'][] = $user->$mobileFieldKey;
                    }
                }
            }
        }

        return array(
            'total' => [
                'count' => $total_count,
                // 'numbers' => array_unique($mobileNumbers),
            ],
            'roles' => $roles,
        );
    }

    /**
     * Get WooCommerce customers
     *
     * @return array|int
     */
    public static function getWooCommerceCustomersNumbers($roles = [])
    {
        $fieldKey = self::getUserMobileFieldName();
        $baseArgs = array(
            'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                'relation' => 'OR',
                array(
                    'key'     => $fieldKey,
                    'value'   => '',
                    'compare' => '!=',
                ),
                array(
                    'key'     => '_billing_phone',
                    'value'   => '',
                    'compare' => '!=',
                ),
            ),
            'fields'     => 'all_with_meta',
        );

        if ($roles) {
            $baseArgs['role__in'] = $roles;
        }

        $baseArgs = apply_filters('wp_sms_wc_mobile_numbers_query_args', $baseArgs);

        $per_page = 300;
        $offset   = 0;
        $numbers  = array();

        do {
            $args           = $baseArgs;
            $args['number'] = $per_page;
            $args['offset'] = $offset;

            $customers = get_users($args);

            if (empty($customers)) {
                break;
            }

            foreach ($customers as $customer) {
                $num = get_user_meta($customer->ID, $fieldKey, true);
                if ($num === '') {
                    $num = get_user_meta($customer->ID, '_billing_phone', true);
                }
                if ($num !== '') {
                    $numbers[] = $num;
                }
            }

            $offset += $per_page;
        } while (count($customers) === $per_page);

        // Backward compatibility with new custom WooCommerce order table.
        if (get_option('woocommerce_custom_orders_table_enabled')) {
            global $wpdb;
            $tableName           = \Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore::get_addresses_table_name();
            $numbersFromNewTable = $wpdb->get_col("SELECT DISTINCT `phone` from {$tableName} where `phone` !=''");
            $numbers             = array_merge($numbers, $numbersFromNewTable);
        }

        $normalizedNumbers = [];
        foreach ($numbers as $number) {
            $normalizedNumber = self::normalizeNumber($number);
            // Use normalized number as key to avoid duplicates
            $normalizedNumbers[$normalizedNumber] = $number;
        }

        return array_values(array_unique($normalizedNumbers));
    }

    /**
     * Get customer mobile number by order id
     *
     * @param $orderId
     * @return string|void
     * @throws Exception
     */
    public static function getWooCommerceCustomerNumberByOrderId($orderId)
    {
        $userId = get_post_meta($orderId, '_customer_user', true);

        if ($userId) {
            $customerMobileNumber = self::getUserMobileNumberByUserId($userId, ['order_id' => $orderId]);

            if ($customerMobileNumber) {
                return $customerMobileNumber;
            }
        }

        $mobile = get_post_meta($orderId, self::getUserMobileFieldName(), true);

        // Backward compatibility, the context of order meta is different with customer
        if (!$mobile) {
            $mobile = get_post_meta($orderId, '_' . self::getUserMobileFieldName(), true);
        }

        // Backward compatibility with new custom WooCommerce order table.
        if (!$mobile && function_exists('wc_get_order')) {
            $order = \wc_get_order($orderId);

            if ($order && method_exists($order, 'get_billing_phone')) {
                $mobile = $order->get_billing_phone();
            }
        }

        return $mobile;
    }

    /**
     * Prepare a list of WP roles
     *
     * @return array
     */
    public static function getListOfRoles()
    {
        $wpsms_list_of_role = array();
        foreach (wp_roles()->role_names as $key_item => $val_item) {
            $wpsms_list_of_role[$key_item] = array(
                "name"  => $val_item,
                "count" => count(self::getUsersMobileNumbers($key_item))
            );
        }

        return $wpsms_list_of_role;
    }

    /**
     * @param $message
     *
     * @return array|string|string[]|null
     */
    public static function makeUrlsShorter($message)
    {
        $regex = "/(http|https)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?/";

        return preg_replace_callback($regex, function ($url) {
            return wp_sms_shorturl($url[0]);
        }, $message);
    }

    public static function isJson($string)
    {
        json_decode($string);

        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * return current admin page url
     *
     * @return string
     */

    public static function getCurrentAdminPageUrl()
    {
        global $wp;

        return add_query_arg($_SERVER['QUERY_STRING'], '', home_url($wp->request) . '/wp-admin/admin.php');
    }

    /**
     * This function check the validity of users' phone numbers. If the number is not available, raise an error
     *
     * @param $mobileNumber
     * @param bool $userId
     * @param bool $isSubscriber
     * @param $groupID
     * @param $subscribeId
     *
     * @return bool|WP_Error
     */
    public static function checkMobileNumberValidity($mobileNumber, $userId = false, $isSubscriber = false, $groupID = false, $subscribeId = false)
    {
        $numberParser = new NumberParser($mobileNumber);
        $mobileNumber = $numberParser->getValidNumber();
        if (is_wp_error($mobileNumber)) {
            return $mobileNumber;
        }

        /**
         * Check whether number is exists in usermeta or sms_subscriber table
         */
        if ($isSubscriber) {
            if ($numberParser::isDuplicateInSubscribers($mobileNumber, $groupID, $subscribeId)) {
                return new WP_Error('is_duplicate', __('This mobile is already registered, please choose another one.', 'wp-sms'));
            }
        } else {
            if ($numberParser::isDuplicateInUsermeta($mobileNumber, $userId)) {
                return new WP_Error('is_duplicate', __('This mobile is already registered, please choose another one.', 'wp-sms'));
            }
        }

        return apply_filters('wp_sms_mobile_number_validity', true, $mobileNumber);
    }


    /**
     * @param $mobile
     *
     * @return string
     */
    public static function sanitizeMobileNumber($mobile)
    {
        return apply_filters('wp_sms_sanitize_mobile_number', sanitize_text_field(trim($mobile)));
    }

    /**
     * This function adds mobile country code to the mobile number if the mobile country code option is enabled.
     *
     * @param $mobileNumber
     * @return mixed|string
     */
    public static function prepareMobileNumber($mobileNumber)
    {
        $international_mode = Option::getOption('international_mobile') ? true : false;
        $country_code       = substr($mobileNumber, 0, 1) == '+' ? true : false;

        if ($international_mode and !$country_code) {
            $mobileNumber = '+' . $mobileNumber;
        }

        return $mobileNumber;
    }

    /**
     * Build an `IN (...)` placeholder fragment plus the matching parameter list for fuzzy
     * mobile-number lookups. Centralizes the prepareMobileNumberQuery + array_fill +
     * implode pattern that was previously copy-pasted across 7 call sites; an off-by-one in
     * any one of those `array_merge` calls would silently mismatch `$wpdb->prepare` params.
     *
     * @param string $number
     * @return array{placeholders: string, params: string[]}
     */
    public static function buildMobileInClause($number)
    {
        $variations   = self::prepareMobileNumberQuery($number);
        $placeholders = implode(', ', array_fill(0, count($variations), '%s'));

        return [
            'placeholders' => $placeholders,
            'params'       => array_values($variations),
        ];
    }

    public static function prepareMobileNumberQuery($number)
    {
        $metaValue = array();

        // Original number as provided
        $metaValue[] = $number;

        // Normalize via NumberParser
        $numberParser     = new \WP_SMS\Components\NumberParser($number);
        $normalizedNumber = $numberParser->getNormalizedNumber();

        if ($number !== $normalizedNumber) {
            $metaValue[] = $normalizedNumber;
        }

        // With and without + prefix
        $withPlus    = '+' . ltrim($normalizedNumber, '+');
        $withoutPlus = ltrim($normalizedNumber, '+');

        $metaValue[] = $withPlus;
        $metaValue[] = $withoutPlus;

        // Also try stripping the configured country code for backward compatibility with local numbers
        $countryCode = Option::getOption('mobile_county_code');
        if (!empty($countryCode) && $countryCode !== '0') {
            $ccDigits = ltrim($countryCode, '+');
            if (strpos($withoutPlus, $ccDigits) === 0) {
                $localNumber = substr($withoutPlus, strlen($ccDigits));
                $metaValue[] = $localNumber;
                $metaValue[] = '0' . $localNumber; // With trunk prefix
            }
        }

        return array_unique($metaValue);
    }

    /**
     * Show Admin Notice
     */
    public static function notice($message, $type = 'info', $dismiss = false, $link = '', $return = false)
    {
        $output = self::loadTemplate('admin/notice.php', [
            'message' => $message,
            'type'    => $type,
            'dismiss' => $dismiss,
            'link'    => $link
        ]);

        if ($return) {
            return $output;
        } else {
            echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }

    /**
     * Add Flash Admin WordPress UI Notice (One-time)
     */
    public static function flashNotice($text, $model = 'success', $redirect = false)
    {
        update_option('wpsms_flash_message', [
            'text'  => $text,
            'model' => $model
        ]);

        if ($redirect) {
            wp_redirect($redirect);
            exit;
        }
    }

    public static function sendMail($subject, $args)
    {
        $adminEmail = get_option('admin_email');
        $message    = self::loadTemplate('email/default.php', $args);
        $headers    = array('Content-Type: text/html; charset=UTF-8');

        return wp_mail($adminEmail, $subject, $message, $headers);
    }

    public static function normalizeNumber($number)
    {
        // Remove all non-digits except leading +
        $number = preg_replace('/[^\d+]/', '', $number);

        // Get the default country code without leading + sign
        $countryCode = substr(Option::getOption('mobile_county_code'), 1);

        // Check if the number starts with + sign
        /*if (strpos($number, '+') === 0) {
            // Remove the + sign from the beginning of each number
            $number = substr($number, 1);
        }*/

        // Check if the number starts with the default country code
        if (!empty($countryCode) && strpos($number, $countryCode) === 0) {
            // Remove the country code from the beginning of each number
            $number = substr($number, strlen($countryCode));
        }

        return $number;
    }

    /**
     * Normalize a phone number to E.164 format, falling back to the original value on failure.
     *
     * Results are statically cached per request — bulk campaigns can call this thousands of
     * times for the same recipient list, and re-instantiating NumberParser is the bottleneck.
     *
     * @param string $mobile
     * @return string
     */
    public static function normalizeToE164($mobile)
    {
        static $cache = [];

        if (!is_string($mobile) || $mobile === '') {
            return $mobile;
        }

        if (isset($cache[$mobile])) {
            return $cache[$mobile];
        }

        $parser = new \WP_SMS\Components\NumberParser($mobile);
        $valid  = $parser->getValidNumber();
        $result = is_wp_error($valid) ? $mobile : $valid;

        // Bound memory against unique-garbage inputs.
        if (count($cache) > 5000) {
            $cache = [];
        }

        $cache[$mobile] = $result;
        return $result;
    }

    /**
     * Detects 4-6 digit short codes (e.g. 80800, 40404) so chokepoints can pass them through
     * unchanged. Short codes have no country code by design, so naively prepending the default
     * CC would route the message to nowhere.
     *
     * @param string $value
     * @return bool
     */
    public static function isShortCode($value)
    {
        if (!is_string($value) || $value === '') {
            return false;
        }

        $trimmed = trim($value);
        if ($trimmed === '' || $trimmed[0] === '+' || $trimmed[0] === '0') {
            return false;
        }

        return (bool) preg_match('/^\d{4,6}$/', $trimmed);
    }

    /**
     * Variant of normalizeToE164() used by dispatch chokepoints — passes short codes through
     * unchanged so marketing/long codes still reach gateways that accept them.
     *
     * @param string $value
     * @return string
     */
    public static function normalizeToE164WithShortCodeGuard($value)
    {
        if (self::isShortCode($value)) {
            return $value;
        }

        return self::normalizeToE164($value);
    }

    /**
     * Attempts to normalize a phone number and returns a structured result so callers can
     * tell whether normalization actually succeeded — `normalizeToE164()` returns the original
     * value on failure with no signal, which makes silent failures invisible to admins.
     *
     * @param string $value
     * @return array{value: string, success: bool, reason: string|null}
     */
    public static function tryNormalizeToE164($value)
    {
        if (!is_string($value) || $value === '') {
            return ['value' => (string) $value, 'success' => false, 'reason' => 'empty'];
        }

        if (self::isShortCode($value)) {
            return ['value' => $value, 'success' => true, 'reason' => null];
        }

        $parser = new \WP_SMS\Components\NumberParser($value);
        $valid  = $parser->getValidNumber();

        if (is_wp_error($valid)) {
            return [
                'value'   => $value,
                'success' => false,
                'reason'  => $valid->get_error_message(),
            ];
        }

        return ['value' => $valid, 'success' => true, 'reason' => null];
    }

    const RECENT_FAILURES_OPTION = 'wpsms_recent_phone_failures';
    const RECENT_FAILURES_LIMIT  = 50;
    const RECENT_FAILURES_DEDUP_WINDOW = 60; // seconds

    /**
     * In-memory buffer for failures recorded during the current request. Flushed once on
     * shutdown via flushNormalizationFailures() so a bulk campaign with thousands of bad
     * recipients triggers a single update_option write rather than thousands.
     *
     * @var array|null  null = no buffer yet (so we know whether to register the shutdown hook)
     */
    private static $failureBuffer = null;

    /**
     * Record a normalization failure so admins can see why their SMS isn't being delivered.
     * Buffered in memory and flushed once on shutdown — see flushNormalizationFailures().
     *
     * Captured value is PII — see GDPR notes in the admin panel UI. Per-source dedup window
     * prevents a runaway integration from spamming the option store.
     *
     * @param string $originalValue
     * @param string $source  e.g. 'cf7:42', 'forminator:7', 'unknown'
     * @param string|null $reason
     * @return void
     */
    public static function recordNormalizationFailure($originalValue, $source = 'unknown', $reason = null)
    {
        if (!is_string($originalValue) || $originalValue === '') {
            return;
        }

        if (self::$failureBuffer === null) {
            self::$failureBuffer = [];
            if (function_exists('add_action')) {
                add_action('shutdown', [self::class, 'flushNormalizationFailures']);
            }
        }

        // Buffer-level dedup: same (source, value) pair within the same request collapses
        // to one entry. Cheap O(n) check against an at-most ~50 entry buffer.
        foreach (self::$failureBuffer as $buffered) {
            if ($buffered['source'] === $source && $buffered['original_value'] === $originalValue) {
                return;
            }
        }

        self::$failureBuffer[] = [
            'original_value' => $originalValue,
            'source'         => $source,
            'timestamp'      => time(),
            'reason'         => $reason,
        ];
    }

    /**
     * Flush the in-memory failure buffer to the persistent option. Called automatically on
     * shutdown; tests call it directly to assert state.
     *
     * @return void
     */
    public static function flushNormalizationFailures()
    {
        if (empty(self::$failureBuffer)) {
            return;
        }

        $existing = get_option(self::RECENT_FAILURES_OPTION, []);
        if (!is_array($existing)) {
            $existing = [];
        }

        // Cross-request dedup: drop new entries whose (source, value) was already recorded
        // within the dedup window. This protects against a runaway integration that fires
        // the same failure across consecutive requests.
        $cutoff = time() - self::RECENT_FAILURES_DEDUP_WINDOW;
        foreach (self::$failureBuffer as $entry) {
            $isDup = false;
            foreach ($existing as $prior) {
                if (
                    isset($prior['source'], $prior['original_value'], $prior['timestamp']) &&
                    $prior['source'] === $entry['source'] &&
                    $prior['original_value'] === $entry['original_value'] &&
                    (int) $prior['timestamp'] >= $cutoff
                ) {
                    $isDup = true;
                    break;
                }
            }
            if (!$isDup) {
                $existing[] = $entry;
            }
        }

        if (count($existing) > self::RECENT_FAILURES_LIMIT) {
            $existing = array_slice($existing, -self::RECENT_FAILURES_LIMIT);
        }

        update_option(self::RECENT_FAILURES_OPTION, $existing, false);
        self::$failureBuffer = [];
    }

    /**
     * Get the recent normalization failures (most-recent-first), used to render the admin panel.
     *
     * @return array
     */
    public static function getRecentNormalizationFailures()
    {
        $failures = get_option(self::RECENT_FAILURES_OPTION, []);
        if (!is_array($failures)) {
            return [];
        }

        return array_reverse($failures);
    }

    /**
     * Clear the recent normalization failures log (admin "dismiss all" action and the GDPR
     * eraser also call this).
     *
     * @return void
     */
    public static function clearRecentNormalizationFailures()
    {
        delete_option(self::RECENT_FAILURES_OPTION);
        self::$failureBuffer = [];
    }

    /**
     * Returns an example phone number formatted in the admin's configured locale, used in
     * validation error messages so the example matches what the user actually expects.
     *
     * @return string
     */
    public static function getPhoneFormatExample()
    {
        $countryCode = Option::getOption('mobile_county_code');
        if (empty($countryCode) || $countryCode === '0') {
            // Generic E.164 example used when no default CC is configured.
            return '+12025550123';
        }

        // The plugin doesn't bundle a per-country example database, so use a stable national
        // segment ('912 345 6789') and just prepend the configured CC. Good enough to make
        // the format obvious in error messages.
        $cc = ltrim($countryCode, '+');
        return '+' . $cc . ' 912 345 6789';
    }

    /**
     * Wraps an E.164 phone value so the leading `+` always lands on the left of the digits in
     * RTL admin layouts (Arabic, Hebrew, Persian). Without this, browsers reorder the `+`
     * to the right side of the number when the surrounding text is RTL.
     *
     * @param string $e164
     * @return string Sanitized HTML
     */
    public static function renderPhoneHtml($e164)
    {
        if (!is_string($e164) || $e164 === '') {
            return '';
        }

        return '<bdi>' . esc_html($e164) . '</bdi>';
    }

    public static function removeDuplicateNumbers($numbers)
    {
        $numbers = array_map('trim', $numbers);

        // Use normalized form as dedup key but preserve original E.164 numbers
        $seen   = [];
        $unique = [];
        foreach ($numbers as $number) {
            $normalized = self::normalizeNumber($number);
            if (!isset($seen[$normalized])) {
                $seen[$normalized] = true;
                $unique[]          = $number;
            }
        }

        return $unique;
    }

    /**
     * Remove certain prefixes from recipient numbers like +, or country code
     *
     * @param array $prefixes Array of prefixes to remove from numbers
     * @param array $numbers Array of numbers
     *
     * @return array
     */
    public static function removeNumbersPrefix($prefix, $numbers)
    {
        $prefixPattern = '/^(' . implode('|', array_map('preg_quote', $prefix)) . ')/';
        return array_map(function ($number) use ($prefixPattern) {
            return preg_replace($prefixPattern, '', $number, 1);
        }, $numbers);
    }

    /**
     * Convert persian/hindi/arabic numbers to english
     *
     * @param string $number
     * @return string
     * @deprecated 3.0.0 Use toEnglishNumerals() instead
     */
    public static function convertNumber($number)
    {
        _deprecated_function(__METHOD__, '7.1.0', 'toEnglishNumerals');
        return NumberParser::toEnglishNumerals($number);
    }

    public static function checkMemoryLimit()
    {
        if (!function_exists('memory_get_peak_usage') or !function_exists('ini_get')) {
            return false;
        }

        $memoryLimit = ini_get('memory_limit');

        if (memory_get_peak_usage(true) > self::convertBytes($memoryLimit)) {
            return true;
        }

        return false;
    }

    /**
     * Check User Access To WP SMS Admin
     *
     * @param string $type [manage | read ]
     * @param string|boolean $export
     * @return bool
     */
    public static function userAccess($type = 'both', $export = false)
    {

        //List Of Default Cap
        $list = array(
            'manage' => array('manage_capability', 'manage_options'),
            'read'   => array('read_capability', 'manage_options')
        );

        //User User Cap
        $cap = 'both';
        if (!empty($type) and array_key_exists($type, $list)) {
            $cap = $type;
        }

        //Check Export Cap name or Validation current_can_user
        if ($export == "cap") {
            return self::ExistCapability(OptionUtil::get($list[$cap][0], $list[$cap][1]));
        }

        //Check Access
        switch ($type) {
            case "manage":
            case "read":
                return current_user_can(self::ExistCapability(OptionUtil::get($list[$cap][0], $list[$cap][1])));
                break;
            case "both":
                foreach (array('manage', 'read') as $c) {
                    if (self::userAccess($c) === true) {
                        return true;
                    }
                }
                break;
        }

        return false;
    }

    /**
     * Validation User Capability
     *
     * @default manage_options
     * @param string $capability Capability
     * @return string 'manage_options'
     */
    public static function ExistCapability($capability)
    {
        global $wp_roles;

        $default_manage_cap = 'manage_options';


        if (!is_object($wp_roles) || !is_array($wp_roles->roles)) {
            return $default_manage_cap;
        }

        foreach ($wp_roles->roles as $role) {
            $cap_list = $role['capabilities'];

            foreach ($cap_list as $key => $cap) {
                if ($capability == $key) {
                    return $capability;
                }
            }
        }

        return $default_manage_cap;
    }

    /**
     * Retrieve the country code associated with the site's configured timezone.
     *
     * @return string|null
     */
    public static function getTimezoneCountry()
    {
        $timezone    = get_option('timezone_string');
        $countryCode = TimeZone::getCountry($timezone);
        return $countryCode;
    }

    /**
     * Filters an array by keeping only the keys specified in the second argument.
     *
     * @param array $array The array to be filtered.
     * @param array $keys The keys to keep in the array.
     * @return array The filtered array.
     */
    public static function filterArrayByKeys($array, $keys)
    {
        return array_intersect_key($array, array_flip($keys));
    }
}
