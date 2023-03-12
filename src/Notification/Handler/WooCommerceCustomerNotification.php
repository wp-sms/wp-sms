<?php

namespace WP_SMS\Notification\Handler;

use WP_SMS\Notification\Notification;

class WooCommerceCustomerNotification extends Notification
{
    protected $customer;

    protected $variables = [
        '%customer_id%'         => 'getId',
        '%customer_email%'      => 'getEmail',
        '%customer_username%'   => 'getUsername',
        '%customer_first_name%' => 'getFirstname',
        '%customer_last_name%'  => 'getLastname',
        '%customer_address%'    => 'getAddress',
    ];

    public function __construct($customerId = false)
    {
        if ($customerId) {
            $this->customer = new \WC_Customer($customerId);
        }
    }

    public function getId()
    {
        return $this->customer->get_id();
    }

    public function getEmail()
    {
        return $this->customer->get_email();
    }

    public function getUsername()
    {
        return $this->customer->get_username();
    }

    public function getFirstname()
    {
        return $this->customer->get_first_name();
    }

    public function getLastname()
    {
        return $this->customer->get_last_name();
    }

    public function getAddress()
    {
        return $this->customer->get_address();
    }
}