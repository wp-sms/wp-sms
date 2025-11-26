<?php

namespace WP_SMS\Notification\Handler;

use WP_SMS\Helper;
use WP_SMS\Notification\Notification;

if (!defined('ABSPATH')) exit;

class OtpNotification extends Notification
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
     * The one-time password (OTP) code.
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
        '%code%'       => 'getCode',
        '%otp%'        => 'getOTP',
        '%user_name%'  => 'getUserName',
        '%first_name%' => 'getFirstName',
        '%last_name%'  => 'getLastName',
        '%full_name%'  => 'getFullName',
        '%site_name%'  => 'getSiteName',
        '%site_url%'   => 'getSiteUrl',
    ];

    /**
     * OtpNotification constructor.
     *
     * @param string $phoneNumber The user's phone number.
     * @param string|int $code The OTP / login verification code.
     */
    public function __construct($phoneNumber, $code)
    {
        $this->phoneNumber = $phoneNumber;
        $this->user        = Helper::getUserByPhoneNumber($phoneNumber);
        $this->code        = $code;
    }

    /**
     * Get the OTP / code (canonical).
     *
     * @return string|int
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Alias for getCode() to support %otp% placeholder.
     *
     * @return string|int
     */
    public function getOTP()
    {
        return $this->getCode();
    }

    /**
     * Get the username associated with the user.
     *
     * @return string|null
     */
    public function getUserName()
    {
        return $this->user->user_login ?? null;
    }

    /**
     * Get the user's first name.
     *
     * @return string|null
     */
    public function getFirstName()
    {
        return $this->user->first_name ?? null;
    }

    /**
     * Get the user's last name.
     *
     * @return string|null
     */
    public function getLastName()
    {
        return $this->user->last_name ?? null;
    }

    /**
     * Get the user's full name (first + last).
     *
     * @return string|null
     */
    public function getFullName()
    {
        $first = $this->getFirstName() ?? '';
        $last  = $this->getLastName() ?? '';
        $full  = trim($first . ' ' . $last);

        return $full !== '' ? $full : null;
    }

    /**
     * Get the WordPress site name.
     *
     * @return string
     */
    public function getSiteName()
    {
        return get_bloginfo('name');
    }

    /**
     * Get the shortened WordPress site URL.
     *
     * @return string
     */
    public function getSiteUrl()
    {
        return wp_sms_shorturl(get_bloginfo('url'));
    }
}