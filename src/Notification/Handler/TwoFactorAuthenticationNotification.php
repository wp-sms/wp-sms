<?php

namespace WP_SMS\Notification\Handler;

use WP_SMS\Helper;
use WP_SMS\Notification\Notification;

class TwoFactorAuthenticationNotification extends Notification
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
    protected $otp;

    /**
     * Template variables and their corresponding getter methods.
     *
     * @var array
     */
    protected $variables = [
        '%otp%'        => 'getOTP',
        '%user_name%'  => 'getUserName',
        '%first_name%' => 'getFirstName',
        '%last_name%'  => 'getLastName',
    ];

    /**
     * TwoFactorAuthenticationNotification constructor.
     *
     * @param string $phoneNumber The user's phone number.
     * @param string|int $otp The generated OTP code.
     */
    public function __construct($phoneNumber, $otp)
    {
        $this->user = Helper::getUserByPhoneNumber($phoneNumber);
        $this->otp  = $otp;
    }

    /**
     * Get the one-time password (OTP) code.
     *
     * @return string|int The OTP code.
     */
    public function getOTP()
    {
        return $this->otp;
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
     * Get the user's first name.
     *
     * @return string|null The user's first name or null if not set.
     */
    public function getFirstName()
    {
        return $this->user->first_name ?? null;
    }

    /**
     * Get the user's last name.
     *
     * @return string|null The user's last name or null if not set.
     */
    public function getLastName()
    {
        return $this->user->last_name ?? null;
    }
}