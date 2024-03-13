<?php

namespace WP_SMS\Services\Subscriber;

use WP_SMS\Notification\NotificationFactory;
use WP_SMS\Option;

class SubscriberManager
{
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

            // @doc https://wp-sms-pro.com/resources/unsubscribe-opt-out-mobile-number-by-url/
            $message = apply_filters('wpsms_welcome_sms_message', $message, $mobile);

            // Fire notification
            $notification = NotificationFactory::getSubscriber($id);
            $notification->send($message, $receiver);
        }
    }
}
