<?php

namespace unit;

use WP_UnitTestCase;
use WP_Error;
use WP_SMS\Option;
use WP_SMS\Services\Email\EmailService;
use WP_SMS\Services\Email\EmailResult;

/**
 * @group email
 */
class EmailServiceTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        add_filter('wp_sms_email_enabled', '__return_true', 10, 2);
        update_option('blogname', 'Test Blog');
        update_option('admin_email', 'admin@example.com');
        \WP_SMS\Option::updateOption('reply_to', 'reply@example.com');

        remove_all_filters('pre_wp_mail');
        remove_all_filters('wp_sms_email_headers');
        remove_all_filters('wp_sms_email_pre_send_args');
        remove_all_actions('wp_mail_failed');
        remove_all_actions('wp_sms_email_post_send');
    }

    public function test_send_returns_disabled_when_setting_off()
    {
        remove_all_filters('wp_sms_email_enabled');
        add_filter('wp_sms_email_enabled', '__return_false', 10, 2);

        $firedPostSend = false;
        add_action('wp_sms_email_post_send', function () use (&$firedPostSend) {
            $firedPostSend = true;
        });

        $result = \WP_SMS\Services\Email\EmailService::send([
            'to'      => 'to@example.com',
            'subject' => 'Hello',
            'body'    => 'Body',
        ]);

        $this->assertFalse($result->success);
        $this->assertSame('Email sending is disabled.', $result->error);
        $this->assertSame('disabled', $result->context['reason'] ?? null);

        $this->assertFalse($firedPostSend, 'wp_sms_email_post_send should not fire when disabled.');
    }



    public function test_send_success_and_filters_applied()
    {
        $sawPreWpMail = false;
        add_filter('pre_wp_mail', function ($shortCircuit, $atts) use (&$sawPreWpMail) {
            $sawPreWpMail = true;

            $headers = isset($atts['headers']) ? (array)$atts['headers'] : [];
            $headersJoined = implode("\n", $headers);

            $this->assertStringContainsStringIgnoringCase('content-type:', $headersJoined, 'Content-Type header should be added by normalizeHeaders().');
            $this->assertStringContainsString('From: Test Blog <admin@example.com>', $headersJoined, 'From header should be added from settings.');
            $this->assertStringContainsString('Reply-To: reply@example.com', $headersJoined, 'Reply-To header should be added from settings.');

            $this->assertSame('OVERRIDDEN SUBJECT', $atts['subject']);

            return true;
        }, 10, 2);

        add_filter('wp_sms_email_headers', function ($headers) {
            $headers[] = 'X-Unit-Test: yes';
            return $headers;
        }, 10, 1);

        add_filter('wp_sms_email_pre_send_args', function ($args) {
            $args['subject'] = 'OVERRIDDEN SUBJECT';
            return $args;
        }, 10, 1);

        $postSendCaptured = null;
        add_action('wp_sms_email_post_send', function ($result, $args, $message, $settings) use (&$postSendCaptured) {
            $postSendCaptured = compact('result', 'args', 'message', 'settings');
        }, 10, 4);

        $message = [
            'to'      => 'receiver@example.com',
            'subject' => 'Original Subject',
            'body'    => '<b>Hello</b> world!',
            'headers' => [],
        ];

        $result = EmailService::send($message);

        $this->assertTrue($result->success, 'Expected a successful send via pre_wp_mail short-circuit.');
        $this->assertNull($result->error);
        $this->assertArrayHasKey('ms', $result->context);
        $this->assertGreaterThanOrEqual(0, $result->context['ms']);

        $this->assertTrue($sawPreWpMail, 'pre_wp_mail should have been invoked.');

        $this->assertIsArray($postSendCaptured);
        $this->assertSame('OVERRIDDEN SUBJECT', $postSendCaptured['args']['subject']);
        $this->assertSame('receiver@example.com', $postSendCaptured['args']['to']);
        $this->assertIsArray($postSendCaptured['settings']);
        $this->assertTrue((bool)$postSendCaptured['settings']['from_email']);
    }

    public function test_send_adds_default_content_type_if_missing()
    {
        add_filter('pre_wp_mail', function ($shortCircuit, $atts) {
            $headers = (array)($atts['headers'] ?? []);
            $joined = implode("\n", $headers);
            $this->assertStringContainsStringIgnoringCase('content-type:', $joined);
            $this->assertStringContainsStringIgnoringCase('charset=utf-8', $joined);
            return true;
        }, 10, 2);

        $result = EmailService::send([
            'to'      => 'to@example.com',
            'subject' => 'Test',
            'body'    => 'Test body',
        ]);

        $this->assertTrue($result->success);
    }

    public function test_send_respects_explicit_headers_and_does_not_duplicate_from_or_reply_to()
    {
        add_filter('pre_wp_mail', function ($shortCircuit, $atts) {
            $headers = (array)$atts['headers'];
            $froms = array_values(array_filter($headers, fn($h) => stripos($h, 'From:') === 0));
            $replyTos = array_values(array_filter($headers, fn($h) => stripos($h, 'Reply-To:') === 0));

            $this->assertCount(1, $froms, 'Should have exactly one From header.');
            $this->assertSame('From: Custom Sender <custom@example.com>', $froms[0]);

            $this->assertCount(1, $replyTos, 'Should have exactly one Reply-To header.');
            $this->assertSame('Reply-To: support@example.com', $replyTos[0]);

            return true;
        }, 10, 2);

        $result = EmailService::send([
            'to'      => 'to@example.com',
            'subject' => 'Test',
            'body'    => 'Test body',
            'headers' => [
                'From: Custom Sender <custom@example.com>',
                'Reply-To: support@example.com',
            ],
        ]);

        $this->assertTrue($result->success);
    }

    public function test_send_failure_unknown_error_when_wp_mail_returns_false_without_wp_error()
    {
        add_filter('pre_wp_mail', fn() => false, 10, 0);

        $result = EmailService::send([
            'to'      => 'fail@example.com',
            'subject' => 'Should fail',
            'body'    => 'X',
        ]);

        $this->assertFalse($result->success);
        $this->assertSame('Unknown error', $result->error, 'Without a captured WP_Error, we expect "Unknown error".');
    }

    public function test_send_catches_exception_thrown_inside_wp_mail_chain()
    {
        add_filter('pre_wp_mail', function () {
            throw new \Exception('Simulated exception!');
        }, 10, 0);

        $result = EmailService::send([
            'to'      => 'to@example.com',
            'subject' => 'boom',
            'body'    => 'x',
        ]);

        $this->assertFalse($result->success);
        $this->assertSame('Simulated exception!', $result->error);
    }

    public function test_emailresult_simple_ctor()
    {
        $r = new EmailResult(true, null, ['ms' => 5]);
        $this->assertTrue($r->success);
        $this->assertNull($r->error);
        $this->assertSame(5, $r->context['ms']);
    }

    public function test_send_uses_settings_defaults_for_from_and_reply_to()
    {
        Option::updateOption('reply_to', 'reply@example.com');

        add_filter('pre_wp_mail', function ($shortCircuit, $atts) {
            $headers = (array)$atts['headers'];

            $this->assertContains('From: Test Blog <admin@example.com>', $headers);
            $this->assertContains('Reply-To: reply@example.com', $headers);

            return true;
        }, 10, 2);

        $res = EmailService::send([
            'to'      => 'to@example.com',
            'subject' => 'x',
            'body'    => 'y',
            'headers' => [],
        ]);

        $this->assertTrue($res->success);
    }

    public function test_headers_are_arrays_even_if_scalar_input()
    {
        add_filter('pre_wp_mail', function ($shortCircuit, $atts) {
            $this->assertIsArray($atts['headers']);
            $this->assertNotEmpty($atts['headers']);
            return true;
        }, 10, 2);

        $res = EmailService::send([
            'to'      => 'to@example.com',
            'subject' => 'x',
            'body'    => 'y',
            'headers' => 'X-Scalar: 1',
        ]);

        $this->assertTrue($res->success);
    }
}
