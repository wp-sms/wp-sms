<?php

namespace WP_SMS\Subscriber;

use WP_SMS\Notification\NotificationFactory;
use WP_SMS\Option;

class SubscriberManager
{
    public function init()
    {
        add_action('wp_sms_add_subscriber', [$this, 'welcomeMessageCallback'], 10, 3);
    }

    /**
     * Add subscriber notification
     *
     * @param $name
     * @param $mobile
     * @param $id
     */
    public function welcomeMessageCallback($name, $mobile, $id)
    {
        // Send welcome message
        if (Option::getOption('newsletter_form_welcome')) {
            $message  = Option::getOption('newsletter_form_welcome_text');
            $receiver = array($mobile);

            // Fire notification
            $notification = NotificationFactory::getSubscriber($id);
            $notification->send($message, $receiver);
        }
    }
}