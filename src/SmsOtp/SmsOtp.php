<?php

namespace WP_SMS\SmsOtp;

use WP_SMS\Install;
use WP_SMS\DynamicResponse\Response;
use DateTime;
use DateInterval;

final class SmsOtp
{
    /**
     * @var boolean
     */
    private $overrideDefaultRateLimit = false;

    /**
     * @var string
     */
    private $phoneNumber;

    /**
     * @var string
     */
    private $agent;

    /**
     * @var DateInterval
     */
    private $rateLimitTimeInterval;

    /**
     * @var integer
     */
    private $rateLimitCount;

    /**
     * @param string $phoneNumber
     * @param string $agent Creation agent name/identifier, to discriminate generate OTPs
     * @param boolean $formatInput Whether to format the $phoneNumber param to a standard format
     */
    public function __construct($phoneNumber, $agent, $formatInput = false)
    {
        $this->phoneNumber = sanitize_text_field($phoneNumber);
        $this->agent       = sanitize_text_field($agent);
    }

    /**
     * Get phone number
     *
     * @return string
     */
    public function getPhoneNumber()
    {
        return $this->phoneNumber;
    }

    /**
     * Get agent
     *
     * @return string
     */
    public function getAgent()
    {
        return $this->agent;
    }

    /**
     * Generate OTP
     *
     * @param integer $length Pass-code's length
     * @return Generator
     */
    public function generate($length = 6)
    {
        $generator = new Generator($this->getPhoneNumber(), $this->getAgent());

        if ($this->overrideDefaultRateLimit) {
            $generator->setRateLimit(
                $this->getRateLimitTimeInterval(),
                $this->getRateLimitCount()
            );
        }

        $generator->limitGeneration();
        $generator->createCode($length);
        $generator->saveIntoDatabase();

        return $generator;
    }

    /**
     * Verify OTP
     *
     * @param string $code
     * @return void
     */
    public function verify($code)
    {
        $verifier = new Verifier($this->getPhoneNumber(), $this->getAgent());

        if ($this->overrideDefaultRateLimit) {
            $verifier->setRateLimit(
                $this->getRateLimitTimeInterval(),
                $this->getRateLimitCount()
            );
        }

        $verifier->limitVerification();
        return $verifier->verify(sanitize_text_field($code));
    }

    /**
     * Check if a number is recently verified using OTP
     *
     * @param DateInterval $interval
     * @return boolean
     */
    public function numberIsRecentlyVerified($interval = null)
    {
        $verifier = new Verifier($this->getPhoneNumber(), $this->getAgent());
        return $verifier->checkIfNumberIsRecentlyVerified($interval);
    }

    /**
     * Change default rate limit configs
     *
     * @param DateInterval $interval
     * @param integer $count
     * @return static
     */
    public function rateLimit($interval, $count)
    {
        $this->overrideDefaultRateLimit = true;

        $this->rateLimitTimeInterval = $interval;
        $this->rateLimitCount        = $count;

        return $this;
    }

    /**
     * Get generation limit time threshold
     *
     * @return DateInterval
     */
    public function getRateLimitTimeInterval()
    {
        return $this->rateLimitTimeInterval;
    }

    /**
     * Get generation limit count
     *
     * @return integer
     */
    public function getRateLimitCount()
    {
        return $this->rateLimitCount;
    }
}
