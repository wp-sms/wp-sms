<?php

namespace WP_SMS\SmsOtp;

use WP_SMS\Install;
use DateTime;
use DateInterval;

final class Verifier
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
     * @var string
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
     * @param DateInterval $period
     * @param integer $count
     * @return void
     */
    public function setRateLimit($period, $count)
    {
        $this->rateLimitTimeInterval = $period;
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
     * Verify an OTP
     *
     * @param string $code
     * @param boolean $bubbleExceptions whether to rethrow caught exceptions
     * @throws Exceptions\TooManyAttemptsException only if $bubbleException is set to true
     * @return boolean
     */
    public function verify($code)
    {
        global $wpdb;

        $otpTable = $wpdb->prefix . Install::TABLE_OTP;

        $match = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$otpTable} WHERE `phone_number` = %s AND `agent` = %s AND `created_at` > %d ORDER BY created_at DESC LIMIT 1",
                [
                    $this->phoneNumber,
                    $this->agent,
                    $this->getRateLimitTimeThreshold()->getTimestamp()
                ]
            )
        );

        if (!empty($match) && $match->code == md5($code)) {
            self::createVerificationAttemptRecord($code, true);
            $wpdb->delete($otpTable, ['ID' => $match->ID]);
            return true;
        } else {
            self::createVerificationAttemptRecord($code, false);
            return false;
        }
    }

    /**
     * Create a verification attempt record in database
     *
     * @param string $attemptedCode
     * @param boolean $result
     * @return mixed
     */
    private function createVerificationAttemptRecord($attemptedCode, $result)
    {
        global $wpdb;

        return $wpdb->insert(
            $wpdb->prefix . Install::TABLE_OTP_ATTEMPTS,
            [
                'phone_number' => $this->phoneNumber,
                'agent'        => $this->agent,
                'code'         => $attemptedCode,
                'result'       => (int) $result,
                'time'         => time(),
            ],
            [
                '%s',
                '%s',
                '%s',
                '%d',
                '%d',
            ]
        );
    }

    /**
     * Limit OTP verification attempts
     *
     * @return void
     * @throws Exceptions\TooManyAttemptsException
     */
    public function limitVerification()
    {
        global $wpdb;

        $tableName = $wpdb->prefix . Install::TABLE_OTP_ATTEMPTS;

        $result = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tableName} WHERE `phone_number` = %s AND `agent` = %s AND `time` > %d AND `result` = 0",
                [
                    $this->phoneNumber,
                    $this->agent,
                    $this->getRateLimitTimeThreshold()->getTimestamp(),
                ]
            )
        );

        if ($result >= $this->getRateLimitCount()) {
            throw new Exceptions\TooManyAttemptsException(esc_html__('Too many verification attempts, please try some other time.', 'wp-sms'));
        }
    }

    /**
     * Check if a number is recently verified
     *
     * @param DateInterval|null $interval Default is 'PT5M'
     * @return boolean
     */
    public function checkIfNumberIsRecentlyVerified($interval = null)
    {
        global $wpdb;

        $interval = $interval ?? new DateInterval('PT5M');

        $otpTable = $wpdb->prefix . Install::TABLE_OTP_ATTEMPTS;

        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$otpTable} WHERE `phone_number` = %s AND `agent` = %s AND `time` > %d AND `result` = 1",
                [
                    $this->phoneNumber,
                    $this->agent,
                    (new DateTime())->sub($interval)->getTimestamp(),
                ]
            )
        );
    }
}
