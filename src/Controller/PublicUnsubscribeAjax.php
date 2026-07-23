<?php

namespace WP_SMS\Controller;

use Exception;
use WP_SMS\Components\NumberParser;
use WP_SMS\Newsletter;
use WP_SMS\Option;
use WP_SMS\Services\Subscriber\SubscriberUtil;

if (!defined('ABSPATH')) exit;

class PublicUnsubscribeAjax extends AjaxControllerAbstract
{
    protected $action = 'wp_sms_unsubscribe';
    /**
     * Public endpoint: visitors unsubscribe from the SMS newsletter without logging in.
     *
     * @var string|null
     */
    protected $capability = null;
    public $requiredFields = [
        'name',
        'mobile',
    ];

    /**
     * @throws Exception
     */
    protected function run()
    {
        // Check GDPR consent if enabled
        if (Option::getOption('gdpr_compliance') === '1' && !$this->get('gdpr_consent')) {
            throw new Exception(esc_html__('Please accept the privacy checkbox to continue.', 'wp-sms'));
        }

        // Throttle requests per client so the endpoint cannot be used to probe
        // or mass-remove records.
        $rateKey  = 'wpsms_unsub_' . md5(sanitize_text_field(wp_unslash(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '')));
        $rateHits = (int) get_transient($rateKey);
        if ($rateHits >= 10) {
            throw new Exception(esc_html__('Too many requests. Please try again later.', 'wp-sms'));
        }
        set_transient($rateKey, $rateHits + 1, 10 * MINUTE_IN_SECONDS);

        $name           = $this->get('name');
        $number         = $this->get('mobile');
        $group_id       = $this->get('group_id', 0);
        $groups_enabled = Option::getOption('newsletter_form_groups');

        // Normalize number input
        $number = NumberParser::toEnglishNumerals($number);

        // Get all matching subscribers
        $subscribers = Newsletter::getSubscriberByMobile($number, false);
        if (empty($subscribers)) {
            throw new Exception(esc_html__('The provided mobile number is not subscribed.', 'wp-sms'));
        }

        $groupIds     = is_array($group_id) ? $group_id : array($group_id);
        $providedName = trim((string) $name);
        $matched      = false;

        foreach ($subscribers as $subscriber) {
            $subscriberNumber = $subscriber->mobile;

            // Ownership check: the request must also carry the name stored for
            // this number, so knowing the number alone is not enough to remove it.
            if (strcasecmp(trim((string) $subscriber->name), $providedName) !== 0) {
                continue;
            }
            $matched = true;

            if ($groups_enabled && !empty(array_filter($groupIds))) {
                $subscriberGroups = Newsletter::getSubscriberGroupsByNumber($subscriberNumber);

                if (empty($subscriberGroups)) {
                    $groupIds = array();
                } elseif (!Newsletter::subscriberExistsInGroup($subscriberNumber, $group_id)) {
                    throw new Exception(esc_html__('This mobile number is not subscribed to the selected group(s).', 'wp-sms'));
                }
            }

            // Perform unsubscription
            if (!empty($groupIds)) {
                foreach ($groupIds as $groupId) {
                    $result = SubscriberUtil::unSubscribe($name, $subscriberNumber, $groupId);
                    if (is_wp_error($result)) {
                        throw new Exception(esc_html($result->get_error_message()));
                    }
                }
            } else {
                $result = SubscriberUtil::unSubscribe($name, $subscriberNumber);
                if (is_wp_error($result)) {
                    throw new Exception(esc_html($result->get_error_message()));
                }
            }
        }

        if (!$matched) {
            throw new Exception(esc_html__('The provided mobile number is not subscribed.', 'wp-sms'));
        }

        /**
         * Filter the unsubscribe success message.
         *
         * Allows customizing the confirmation message shown after a successful
         * unsubscribe, for example to show a different message per group.
         *
         * @since 7.2.6
         *
         * @param string    $message  The default success message.
         * @param array|int $groupIds The group ID(s) the number was unsubscribed from (empty for all groups).
         * @param string    $number   The unsubscribed mobile number.
         */
        $message = apply_filters(
            'wpsms_unsubscribe_success_message',
            esc_html__('You have successfully unsubscribed from the newsletter.', 'wp-sms'),
            $groupIds,
            $number
        );

        wp_send_json_success($message);
    }

}
