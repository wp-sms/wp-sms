<?php

namespace WP_SMS\Notification\Handler;

use WP_SMS\Notification\Notification;

class WordPressUserNotification extends Notification
{
    protected $user;

    protected $variables = [
        '%user_id%'       => 'getId',
        '%user_login%'    => 'getLogin',
        '%user_email%'    => 'getEmail',
        '%date_register%' => 'getDateRegister',
        '%user_url%'      => 'getUrl',
        '%display_name%'  => 'getDisplayName',
        '%first_name%'    => 'getFirstName',
        '%last_name%'     => 'getLastName',
        '%user_role%'     => 'getRole'
    ];

    public function __construct($userId = false)
    {
        if ($userId) {
            $this->user = get_user_by('id', $userId);
        }
    }

    public function getId()
    {
        return $this->user->ID;
    }

    public function getLogin()
    {
        return $this->user->user_login;
    }

    public function getEmail()
    {
        return $this->user->user_email;
    }

    public function getDateRegister()
    {
        return $this->user->user_registered;
    }

    public function getUrl()
    {
        return wp_sms_shorturl($this->user->user_url);
    }

    public function getDisplayName()
    {
        return $this->user->display_name;
    }

    public function getFirstName()
    {
        return $this->user->first_name;
    }

    public function getLastName()
    {
        return $this->user->last_name;
    }

    public function getRole()
    {
        return $this->user->roles[0];
    }
}