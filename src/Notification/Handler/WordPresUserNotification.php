<?php

namespace WP_SMS\Notification\Handler;

use WP_SMS\Notification\Notification;

class WordPresUserNotification extends Notification
{
    protected $user;

    protected $variables = [
        '%user_id%' => 'getUserId',
    ];

    public function __construct($userId = false)
    {
        if ($userId) {
            $this->user = get_user_by('id', $userId);
        }
    }

    public function getUserId()
    {
        return $this->user->ID;
    }
}