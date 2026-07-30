<?php

namespace unit;

use WP_SMS\Services\Subscriber\SubscriberUtil;
use WP_UnitTestCase;

class SubscriberVerificationThrottleTest extends WP_UnitTestCase
{
    protected $mobile = '09120000123';
    protected $clientIp = '192.0.2.10';

    protected function setUp(): void
    {
        parent::setUp();

        global $wpdb;

        $_SERVER['REMOTE_ADDR'] = $this->clientIp;
        $wpdb->insert(
            $wpdb->prefix . 'sms_subscribes',
            array(
                'date'          => current_time('mysql'),
                'name'          => 'Pending Subscriber',
                'mobile'        => $this->mobile,
                'status'        => 0,
                'activate_key'  => 1234,
                'custom_fields' => '',
                'group_ID'      => 0,
            ),
            array('%s', '%s', '%s', '%d', '%d', '%s', '%d')
        );
    }

    protected function tearDown(): void
    {
        global $wpdb;

        $attemptKey = $this->getAttemptKey();
        delete_transient($attemptKey);
        delete_option($this->getLockName($attemptKey));
        $wpdb->delete($wpdb->prefix . 'sms_subscribes', array('mobile' => $this->mobile), array('%s'));
        unset($_SERVER['REMOTE_ADDR']);

        parent::tearDown();
    }

    public function test_concurrent_failed_verification_fails_closed_while_counter_is_locked()
    {
        $attemptKey = $this->getAttemptKey();
        $lockName   = $this->getLockName($attemptKey);

        set_transient($attemptKey, 9, 15 * MINUTE_IN_SECONDS);
        add_option($lockName, 'parallel-request|' . time(), '', 'no');

        $result = SubscriberUtil::verifySubscriber('Pending Subscriber', $this->mobile, '9999', '0');

        $this->assertWPError($result);
        $this->assertSame('Too many attempts. Please try again later.', $result->get_error_message());
        $this->assertSame(9, (int) get_transient($attemptKey));
    }

    public function test_ten_failed_verification_attempts_are_counted_and_the_next_is_blocked()
    {
        $attemptKey = $this->getAttemptKey();
        set_transient($attemptKey, 9, 15 * MINUTE_IN_SECONDS);

        $tenth = SubscriberUtil::verifySubscriber('Pending Subscriber', $this->mobile, '9999', '0');
        $this->assertWPError($tenth);
        $this->assertSame('Activation code is wrong!', $tenth->get_error_message());
        $this->assertSame(10, (int) get_transient($attemptKey));

        $eleventh = SubscriberUtil::verifySubscriber('Pending Subscriber', $this->mobile, '9999', '0');
        $this->assertWPError($eleventh);
        $this->assertSame('Too many attempts. Please try again later.', $eleventh->get_error_message());
        $this->assertSame(10, (int) get_transient($attemptKey));
    }

    public function test_successful_verification_clears_the_attempt_counter()
    {
        $attemptKey = $this->getAttemptKey();
        set_transient($attemptKey, 4, 15 * MINUTE_IN_SECONDS);

        $result = SubscriberUtil::verifySubscriber('Pending Subscriber', $this->mobile, '1234', '0');

        $this->assertSame('Your subscription done successfully!', $result);
        $this->assertFalse(get_transient($attemptKey));
    }

    private function getAttemptKey()
    {
        return 'wpsms_verify_attempts_' . hash('sha256', $this->clientIp . '|' . $this->mobile);
    }

    private function getLockName($attemptKey)
    {
        return 'wpsms_verify_lock_' . hash('sha256', $attemptKey);
    }
}
