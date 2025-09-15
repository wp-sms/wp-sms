<?php

namespace unit;

use WP_UnitTestCase;
use WP_SMS\Services\OTP\Delivery\PhoneNumber\SmsChannel;
use WP_SMS\Services\OTP\Delivery\PhoneNumber\Templating\SmsTemplate;

class SmsChannelTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        update_option('blogname', 'Test Blog');

        remove_all_actions('wpsms_log_event');
        remove_all_actions('wpsms_sms_before_send');
        remove_all_filters('wpsms_sms_transport_send');
    }

    public function test_send_template_emits_before_hook_and_success_log_when_transport_succeeds()
    {
        $sawBefore = false;
        $sawLog    = false;

        add_action('wpsms_sms_before_send', function ($to, $message, $ctx) use (&$sawBefore) {
            $sawBefore = true;
            $this->assertSame('+15551230000', $to);
            $this->assertIsString($message);
            $this->assertNotEmpty($message);
            $this->assertSame('otp_code', $ctx['template']);
        }, 10, 3);

        add_action('wpsms_log_event', function ($event, $payload) use (&$sawLog) {
            if ($event === 'sms_delivery_success') {
                $sawLog = true;
                $this->assertSame('otp_code', $payload['template']);
            }
        }, 10, 2);

        // short-circuit actual transport to a "success" result
        add_filter('wpsms_sms_transport_send', function ($short, $to, $msg, $ctx) {
            return true; // truthy => success
        }, 10, 4);

        $channel = new SmsChannel();

        $ok = $channel->send('+15551230000', '', [
            'template'           => SmsTemplate::TYPE_OTP_CODE,
            'otp_code'           => '654321',
            'expires_in_minutes' => '2',
            'user_display_name'  => 'Jane',
        ]);

        $this->assertTrue($ok);
        $this->assertTrue($sawBefore, 'Expected wpsms_sms_before_send to fire');
        $this->assertTrue($sawLog, 'Expected success log event');
    }

    public function test_send_without_template_uses_plaintext_normalization_and_logs_error_on_failure()
    {
        $sawBefore = false;
        $sawError  = false;
        $captured  = null;

        add_action('wpsms_sms_before_send', function ($to, $message) use (&$sawBefore, &$captured) {
            $sawBefore = true;
            $captured  = $message;
        }, 10, 2);

        add_action('wpsms_log_event', function ($event, $payload) use (&$sawError) {
            if ($event === 'sms_delivery_error') {
                $sawError = true;
                $this->assertSame('raw', $payload['template']);
                $this->assertNotEmpty($payload['error']);
            }
        }, 10, 2);

        // force a failure from the transport
        add_filter('wpsms_sms_transport_send', function () {
            return false; // falsy => failure
        });

        $channel = new SmsChannel();

        $ok = $channel->send('+15551239999', " Hi \n\n there\t\tworld "); // raw body, no template

        $this->assertFalse($ok);
        $this->assertTrue($sawBefore, 'Expected wpsms_sms_before_send to fire');

        // message should be whitespace-normalized to a single line for SMS
        $this->assertSame('Hi there world', $captured);
        $this->assertTrue($sawError, 'Expected error log event');
    }

    public function test_context_is_augmented_with_site_and_user_defaults()
    {
        // Capture the message produced by the template to inspect resolved placeholders.
        $captured = null;

        add_action('wpsms_sms_before_send', function ($to, $message) use (&$captured) {
            $captured = $message;
        }, 10, 2);

        // success short-circuit
        add_filter('wpsms_sms_transport_send', function () {
            return true;
        });

        $channel = new SmsChannel();

        // note: intentionally omitting user_display_name to hit the default "User"
        $ok = $channel->send('+15550001111', '', [
            'template'           => SmsTemplate::TYPE_OTP_CODE,
            'otp_code'           => '111222',
            'expires_in_minutes' => '3',
        ]);

        $this->assertTrue($ok);
        // The rendered SMS should contain Test Blog and User
        $this->assertStringContainsString('Test Blog', $captured, 'site_name should be auto-filled');
        $this->assertStringContainsString('User', $captured, 'user_display_name default should be "User"');
    }
}
