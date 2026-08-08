<?php

namespace WP_SMS\Services\Subscriber;

use WP_SMS\Notification\NotificationFactory;
use WP_SMS\Option;

if (!defined('ABSPATH')) exit;

class SubscriberManager
{
    /**
     * Error from the most recent welcome message, or null when the last one went out.
     *
     * The welcome message is sent from inside the wp_sms_add_subscriber action, which
     * runs while the subscriber is still being created, so the caller that started the
     * subscription can read the outcome here and decide what to do about it.
     *
     * @var \WP_Error|null
     */
    private static $welcomeMessageError = null;

    public function init()
    {
        add_action('wp_sms_add_subscriber', [$this, 'welcomeMessageCallback'], 10, 4);
        add_action('wp_sms_verify_subscriber', [$this, 'welcomeMessageCallback'], 10, 4);
    }

    /**
     * Add subscriber notification
     *
     * @param $name
     * @param $mobile
     * @param $status
     * @param $id
     */
    public function welcomeMessageCallback($name, $mobile, $status, $id)
    {

        // Send welcome message
        if ($status == '1' && Option::getOption('newsletter_form_welcome')) {
            $message  = Option::getOption('newsletter_form_welcome_text');
            $receiver = array($mobile);

            // @doc https://wsms.io/docs/unsubscribe-url
            $message = apply_filters('wpsms_welcome_sms_message', $message, $mobile);

            // Fire notification
            $notification = NotificationFactory::getSubscriber($id);
            $result       = $notification->send($message, $receiver);

            self::$welcomeMessageError = is_wp_error($result) ? $result : null;
        }
    }

    /**
     * Error from the last welcome message, if there was one
     *
     * @return \WP_Error|null
     */
    public static function getWelcomeMessageError()
    {
        return self::$welcomeMessageError;
    }

    /**
     * Clear the stored welcome message error
     *
     * Call this before a subscription starts, so the result read afterwards belongs to
     * that subscription and not to an earlier one in the same request.
     */
    public static function forgetWelcomeMessageError()
    {
        self::$welcomeMessageError = null;
    }
}
