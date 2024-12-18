<?php

namespace WP_SMS\SmsOtp;

use WP_SMS\Install;
use DateTime;
use DateInterval;

final class Generator
{
    /**
     * @var DateInterval
     */
    private $rateLimitTimeInterval;

    /**
     * @var integer
     */
    private $rateLimitCount = 5;

    /**
     * @var string
     */
    private $agent;

    /**
     * @var $code
     */
    private $code;

    /**
     * @var string $phoneNumber
     */
    private $phoneNumber;

    /**
     * @param string $phoneNumber
     * @param string $agent
     */
    public function __construct($phoneNumber, $agent)
    {
        $this->phoneNumber = $phoneNumber;
        $this->agent       = $agent;
    }

    /**
     * Set generation rate limit
     *
     * @param DateInterval $interval
     * @param integer $count
     * @return void
     */
    public function setRateLimit($interval, $count)
    {
        $this->rateLimitTimeInterval = $interval;
        $this->rateLimitCount        = $count;
    }

    /**
     * Get generation limit time threshold
     *
     * @return DateInterval
     */
    public function getRateLimitTimeInterval()
    {
        return apply_filters('wp_sms_otp_rate_limit_time_interval', new DateInterval('PT5M'));
    }

    /**
     * Get verification time threshold
     *
     * @return DateTime
     */
    public function getRateLimitTimeThreshold()
    {
        return (new DateTime())->sub($this->getRateLimitTimeInterval());
    }

    /**
     * Get generation limit count
     *
     * @return integer
     */
    public function getRateLimitCount()
    {
        return apply_filters('wp_sms_otp_rate_limit_count', $this->rateLimitCount);
    }

    /**
     * Get passcode
     *
     * @return string $passcode
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Get phone number
     *
     * @return string $phoneNumber
     */
    public function getPhoneNumber()
    {
        return $this->phoneNumber;
    }

    /**
     * Create pass code
     *
     * @param integer $length
     * @return void
     * @throws Exceptions\InvalidArgumentException
     */
    public function createCode($length)
    {
        $length = apply_filters('wp_sms_sms_otp_length', $length);

        if ($length < 2 || $length > 10) {
            throw new Exceptions\InvalidArgumentException(esc_html__('Provided $length argument must be between 4 and 10.', 'wp-sms'));
        }

        $hash = bin2hex(openssl_random_pseudo_bytes(16));

        $values = array_values(unpack('C*', $hash));

        $offset = ($values[\count($values) - 1] & 0xF);
        $code   = ($values[$offset + 0] & 0x7F) << 24 | ($values[$offset + 1] & 0xFF) << 16 | ($values[$offset + 2] & 0xFF) << 8 | ($values[$offset + 3] & 0xFF);
        $otp    = $code % (10 ** $length);

        $this->code = str_pad((string)$otp, $length, '0', STR_PAD_LEFT);
    }

    /**
     * Limit OTP generation rate
     *
     * @return void
     * @throws Exceptions\OtpLimitExceededException
     */
    public function limitGeneration()
    {
        global $wpdb;

        $tableName = $wpdb->prefix . Install::TABLE_OTP;

        $result = (int)$wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tableName} WHERE `phone_number` = %s AND `agent` = %s AND `created_at` > %d",
                [
                    $this->phoneNumber,
                    $this->agent,
                    $this->getRateLimitTimeThreshold()->getTimestamp()
                ]
            )
        );

        if ($result >= $this->getRateLimitCount()) {
            throw new Exceptions\OtpLimitExceededException(esc_html__('OTPs generated for this number has reached its limit, please try some other time.', 'wp-sms'));
        }
    }

    /**
     * Insert record into database
     *
     * @return int|false
     */
    public function saveIntoDatabase()
    {
        global $wpdb;

        return $wpdb->insert(
            $wpdb->prefix . Install::TABLE_OTP,
            [
                'phone_number' => $this->phoneNumber,
                'code'         => md5($this->code),
                'agent'        => $this->agent,
                'created_at'   => time(),
            ],
            [
                '%s',
                '%s',
                '%s',
                '%d',
            ]
        );
    }
}
