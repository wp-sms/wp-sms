<?php

namespace WP_SMS\Notification\Handler\BuddyPress;

use WP_SMS\Notification\Notification;

if (!defined('ABSPATH')) exit;

class BuddyPressWelcomeNotification extends Notification
{
    /**
     * The WordPress user object associated with the welcome notification.
     *
     * @var \WP_User|false
     */
    protected $user;

    /**
     * Template variables and their corresponding getter methods.
     *
     * @var array
     */
    protected $variables = [
        '%user_login%'   => 'getUserLogin',
        '%user_email%'   => 'getUserEmail',
        '%display_name%' => 'getDisplayName'
    ];

    /**
     * BuddyPressWelcomeNotification constructor.
     *
     * @param \WP_User $user The WordPress user object.
     */
    public function __construct($user)
    {
        $this->user = $user;
    }

    /**
     * Get the username (login) of the user.
     *
     * @return string|null
     */
    public function getUserLogin()
    {
        return $this->user->user_login ?? null;
    }

    /**
     * Get the email address of the user.
     *
     * @return string|null
     */
    public function getUserEmail()
    {
        return $this->user->user_email ?? null;
    }

    /**
     * Get the display name of the user.
     *
     * @return string|null
     */
    public function getDisplayName()
    {
        return $this->user->display_name ?? null;
    }
}