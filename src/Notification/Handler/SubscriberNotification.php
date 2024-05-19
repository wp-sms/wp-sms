<?php

namespace WP_SMS\Notification\Handler;

use WP_SMS\Newsletter;
use WP_SMS\Notification\Notification;

class SubscriberNotification extends Notification
{
    protected $subscriber;

    protected $variables = [
        '%subscriber_name%'          => 'getSubscriberName',
        '%subscriber_mobile%'        => 'getSubscriberMobile',
        '%subscriber_status%'        => 'getSubscriberStatus',
        '%subscriber_group%'         => 'getSubscriberGroup',
        '%subscriber_custom_fields%' => 'getSubscriberCustomFields',
        '%subscriber_date%'          => 'getSubscriberDate',
        '%unsubscribe_url%'          => 'getUnsubscribeUrl'
    ];

    public function __construct($subscriberId = false)
    {
        if ($subscriberId) {
            $this->subscriber = Newsletter::getSubscriber($subscriberId);
        }
    }

    public function getSubscriberName()
    {
        return mb_strimwidth($this->subscriber->name, 0, 30);
    }

    public function getSubscriberMobile()
    {
        return $this->subscriber->mobile;
    }

    public function getSubscriberStatus()
    {
        return $this->subscriber->status;
    }

    public function getSubscriberGroup()
    {
        $group = Newsletter::getGroup($this->subscriber->group_ID);
        return $group->name;
    }

    public function getSubscriberCustomFields()
    {
        return implode(', ', unserialize($this->subscriber->custom_fields));
    }

    public function getSubscriberDate()
    {
        return $this->subscriber->date;
    }

    public function getUnsubscribeUrl()
    {
        return Newsletter::generateUnSubscribeUrlByNumber($this->subscriber->mobile);
    }
}
