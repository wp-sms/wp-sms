<?php

namespace WP_SMS\Services\Subscriber;

use WP_SMS\Components\NumberParser;
use WP_SMS\Helper;
use WP_SMS\Newsletter;
use WP_SMS\Option;

if (!defined('ABSPATH')) exit;

/**
 * @todo this old-level class should be refactored, have to keep it for now but let's get rid of it in the future
 */
class SubscriberUtil
{
    /**
     * Subscribe User
     *
     * @param $name
     * @param $mobile
     * @param bool $group
     * @param array $customFields
     * @return array|string|\WP_Error
     */
    public static function subscribe($name, $mobile, $group = false, $customFields = array())
    {
        if (empty($name) || empty($mobile)) {
            return new \WP_Error('subscribe', esc_html__('Name and Mobile Number are required!', 'wp-sms'));
        }

        // Use NumberParser instance
        $numberParser = new NumberParser($mobile);
        $validNumber = $numberParser->getValidNumber();

        if (is_wp_error($validNumber)) {
            return $validNumber;
        }
        $mobile = $validNumber;
        // Delete inactive subscribes with this number
        Newsletter::deleteInactiveSubscribersByMobile($mobile);

        $groupIds = wp_unslash($group);

        if (!is_null($groupIds)) {
            $groupIds = json_decode($groupIds);
        }

        if (!is_array($groupIds)) {
            $groupIds = array($groupIds);
        }

        $groupIds = array_map('intval', $groupIds);

        $gateway_name = Option::getOption('gateway_name');

        $addedIds = [];

        if (Option::getOption('newsletter_form_verify') and $gateway_name) {
            // Check gateway setting
            if (!$gateway_name) {
                // Return response
                return new \WP_Error('subscribe', esc_html__('Service provider is not available for send activate key to your mobile. Please contact with site.', 'wp-sms'));
            }

            $key = wp_rand(1000, 9999);

            foreach ($groupIds as $groupId) {
                // Add subscribe to database
                $result = Newsletter::addSubscriber($name, $mobile, $groupId, '0', $key, $customFields);
                if ($result['result'] == 'error') {
                    self::rollbackSubscribers($addedIds);

                    // Return response
                    return new \WP_Error('subscribe', $result['message']);
                }

                $addedIds[] = $result['id'];
            }

            // translators: %s: Activation code
            $sendResult = wp_sms_send($mobile, sprintf(esc_html__('Your activation code: %s', 'wp-sms'), $key));

            // The activation code is the only way to complete this subscription, so a number
            // the gateway refused leaves a row nobody can ever activate. Undo it and tell the
            // visitor what actually happened instead of claiming the code is on its way.
            if (is_wp_error($sendResult)) {
                self::rollbackSubscribers($addedIds);

                return new \WP_Error('subscribe', $sendResult->get_error_message());
            }

            // Return response
            return esc_html__('To activate your subscription, the activation has been sent to your number.', 'wp-sms');
        } else {
            SubscriberManager::forgetWelcomeMessageError();

            foreach ($groupIds as $groupId) {
                // Add subscribe to database
                $result = Newsletter::addSubscriber($name, $mobile, $groupId, '1', null, $customFields);
                if ($result['result'] == 'error') {
                    self::rollbackSubscribers($addedIds);

                    // Return response
                    return new \WP_Error('subscribe', $result['message']);
                }

                $addedIds[] = $result['id'];
            }

            // addSubscriber fires wp_sms_add_subscriber, which is where the welcome message
            // goes out, so by now we know whether the number can actually receive anything.
            // A number the gateway refuses (an opt-out at the provider, for example) would
            // otherwise sit on the list forever without ever receiving a campaign.
            $welcomeError = SubscriberManager::getWelcomeMessageError();

            if (is_wp_error($welcomeError) && apply_filters('wp_sms_rollback_subscriber_on_failed_welcome', true, $mobile, $welcomeError)) {
                self::rollbackSubscribers($addedIds);

                return new \WP_Error('subscribe', $welcomeError->get_error_message());
            }

            // Return response
            return esc_html__('Your mobile number has been successfully subscribed.', 'wp-sms');
        }
    }

    /**
     * Remove subscribers added earlier in this request
     *
     * Used when a subscription cannot be completed, so a half finished attempt does not
     * leave rows behind. Deletes by ID on purpose, so an existing subscription for the
     * same number is never touched.
     *
     * @param array $subscriberIds
     *
     * @return void
     */
    private static function rollbackSubscribers($subscriberIds)
    {
        global $wpdb;

        $subscriberIds = array_filter(array_map('intval', (array)$subscriberIds));

        if (empty($subscriberIds)) {
            return;
        }

        foreach ($subscriberIds as $subscriberId) {
            $wpdb->delete("{$wpdb->prefix}sms_subscribes", ['ID' => $subscriberId], ['%d']);
        }
    }

    /**
     * Unsubscribe user
     *
     * @param $name
     * @param $mobile
     * @param null $group
     *
     * @return array|string
     */
    public static function unSubscribe($name, $mobile, $group = false)
    {
        if (empty($name) or empty($mobile)) {
            return new \WP_Error('unsubscribe', esc_html__('Name and Mobile Number are required!', 'wp-sms'));
        }

        // Delete subscriber
        $result = Newsletter::deleteSubscriberByNumber($mobile, $group);

        // Check result
        if ($result['result'] == 'error') {
            // Return response
            return new \WP_Error('unsubscribe', $result['message']);
        }

        return $result['message'];
    }

    /**
     * Verify Subscriber
     *
     * @param $name
     * @param $mobile
     * @param $activation
     * @param $groupId
     * @return array|string
     */
    public static function verifySubscriber($name, $mobile, $activation, $groupId = 0)
    {
        global $wpdb;

        if (empty($name) or empty($mobile) or empty($activation)) {
            return new \WP_Error('unsubscribe', esc_html__('The required parameters must be valued!', 'wp-sms'));
        }

        $db_prepare = $wpdb->prepare("SELECT * FROM `{$wpdb->prefix}sms_subscribes` WHERE `mobile` = %s AND `status` = %d", $mobile, 0);

        $groupId = json_decode(stripslashes($groupId), true);

        if (is_array($groupId)) {
            $groupId = $groupId[0];
        }

        $updateCondition = array('mobile' => $mobile);
        if ($groupId and $groupId !== 0) {
            $db_prepare                  .= $wpdb->prepare(" AND group_ID = %d", $groupId);
            $updateCondition['group_ID'] = $groupId;
        }
        $check_mobile = $wpdb->get_row($db_prepare); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        if ($check_mobile) {

            // Throttle guesses so the activation code cannot be enumerated. The
            // counter is per client and number, so a third party sending wrong
            // codes cannot lock the genuine subscriber out of confirming.
            $clientIp   = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
            $attemptKey = 'wpsms_verify_attempts_' . hash('sha256', $clientIp . '|' . $mobile);
            $isValid    = hash_equals((string) $check_mobile->activate_key, (string) $activation);
            $attempt    = self::recordVerificationAttempt($attemptKey, $isValid);

            if (is_wp_error($attempt)) {
                return $attempt;
            }

            if (!$isValid) {

                // Return response
                return new \WP_Error('verify_subscriber', esc_html__('Activation code is wrong!', 'wp-sms'));
            }

            // Check the mobile number is string or integer
            if (strpos($mobile, '+') !== false) {
                $result = $wpdb->update("{$wpdb->prefix}sms_subscribes", array('status' => '1'), $updateCondition, array('%d', '%d'), array('%s'));
            } else {
                $result = $wpdb->update("{$wpdb->prefix}sms_subscribes", array('status' => '1'), $updateCondition, array('%d', '%d'), array('%d'));
            }

            if ($result) {
                do_action('wp_sms_verify_subscriber', $name, $mobile, 1, $check_mobile->ID);

                // Return response
                return esc_html__('Your subscription done successfully!', 'wp-sms');
            }
        }

        return new \WP_Error('verify_subscriber', esc_html__('Not found the number!', 'wp-sms'));
    }

    /**
     * Atomically record a verification attempt or reset a successful one.
     *
     * A short-lived option lock serializes the transient read-modify-write for
     * both database-backed transients and persistent object-cache backends.
     * Lock contention fails closed so parallel requests cannot bypass the cap.
     *
     * @param string $attemptKey Transient key for the client and mobile number.
     * @param bool   $isValid    Whether the submitted activation code is valid.
     *
     * @return int|\WP_Error The new attempt count, or an error when unavailable or limited.
     */
    private static function recordVerificationAttempt($attemptKey, $isValid)
    {
        global $wpdb;

        $lockName  = 'wpsms_verify_lock_' . hash('sha256', $attemptKey);
        $lockValue = wp_generate_uuid4() . '|' . time();
        $acquired  = false;

        for ($retry = 0; $retry < 20; $retry++) {
            if (add_option($lockName, $lockValue, '', 'no')) {
                $acquired = true;
                break;
            }

            $existingLock = (string) get_option($lockName, '');
            $lockParts    = explode('|', $existingLock);
            $lockCreated  = isset($lockParts[1]) ? (int) $lockParts[1] : 0;

            // Recover from a request that terminated while holding the lock.
            if ($lockCreated > 0 && $lockCreated < time() - 5) {
                $deleted = $wpdb->delete(
                    $wpdb->options,
                    array('option_name' => $lockName, 'option_value' => $existingLock),
                    array('%s', '%s')
                );

                if ($deleted) {
                    wp_cache_delete($lockName, 'options');
                    continue;
                }
            }

            usleep(50000);
        }

        if (!$acquired) {
            return new \WP_Error('verify_subscriber', esc_html__('Too many attempts. Please try again later.', 'wp-sms'));
        }

        try {
            $attemptCount = (int) get_transient($attemptKey);

            if ($attemptCount >= 10) {
                return new \WP_Error('verify_subscriber', esc_html__('Too many attempts. Please try again later.', 'wp-sms'));
            }

            if ($isValid) {
                delete_transient($attemptKey);

                return 0;
            }

            if (!set_transient($attemptKey, $attemptCount + 1, 15 * MINUTE_IN_SECONDS)) {
                return new \WP_Error('verify_subscriber', esc_html__('Too many attempts. Please try again later.', 'wp-sms'));
            }

            return $attemptCount + 1;
        } finally {
            if (get_option($lockName) === $lockValue) {
                delete_option($lockName);
            }
        }
    }
}
