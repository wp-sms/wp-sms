<?php

namespace WP_SMS\Notification\Handler;

use WP_SMS\Helper;
use WP_SMS\Notification\Notification;

class LoginWithSmsNotification extends Notification
{
    /**
     * The WordPress user object associated with the phone number.
     *
     * @var \WP_User|null
     */
    protected $user;

    /**
     * The recipient's phone number.
     *
     * @var string
     */
    protected $phoneNumber;

    /**
     * The login verification code (OTP).
     *
     * @var string|int
     */
    protected $code;

    /**
     * Template variables and their corresponding getter methods.
     *
     * @var array
     */
    protected $variables = [
        '%code%'      => 'getCode',
        '%user_name%' => 'getUserName',
        '%full_name%' => 'getFullName',
        '%site_name%' => 'getSiteName',
        '%site_url%'  => 'getSiteUrl'
    ];

    /**
     * LoginWithSmsNotification constructor.
     *
     * @param string $phoneNumber The user's phone number.
     * @param string|int $code The login verification code (OTP).
     */
    public function __construct($phoneNumber, $code)
    {
        $this->user = Helper::getUserByPhoneNumber($phoneNumber);
        $this->code = $code;
    }

    /**
     * Get the login verification code (OTP).
     *
     * @return string|int The login code.
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Get the username associated with the user.
     *
     * @return string|null The user's login name or null if unavailable.
     */
    public function getUserName()
    {
        return $this->user->user_login ?? null;
    }

    /**
     * Get the user's full name (first and last name combined).
     *
     * @return string|null The user's full name or null if unavailable.
     */
    public function getFullName()
    {
        $first = $this->user->first_name ?? '';
        $last  = $this->user->last_name ?? '';

        return trim("$first $last");
    }

    /**
     * Get the WordPress site name.
     *
     * @return string The site name.
     */
    public function getSiteName()
    {
        return get_bloginfo('name');
    }

    /**
     * Get the shortened WordPress site URL.
     *
     * @return string The site URL (possibly shortened).
     */
    public function getSiteUrl()
    {
        return wp_sms_shorturl(get_bloginfo('url'));
    }
}