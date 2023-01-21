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
    ];

    public function __construct($subscriberId = false)
    {
        if ($subscriberId) {
            $this->subscriber = Newsletter::getSubscriber($subscriberId);
        }
    }

    public function getSubscriberName()
    {
        return $this->subscriber->name;
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
        return $this->subscriber->group_ID;
    }

    public function getSubscriberCustomFields()
    {
        return implode(', ', unserialize($this->subscriber->custom_fields));
    }

    public function getSubscriberDate()
    {
        return $this->subscriber->date;
    }
}