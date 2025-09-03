<?php

namespace unit;

use WP_UnitTestCase;
use WP_SMS\Services\OTP\Delivery\Email\EmailChannel;
use WP_SMS\Services\OTP\Delivery\Email\Templating\EmailTemplate;

class EmailChannelTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        update_option('blogname', 'Test Blog');
        add_filter('wp_sms_email_enabled', '__return_true', 10, 2);

        remove_all_filters('pre_wp_mail');
        remove_all_actions('wpsms_log_event');
    }

    public function test_send_plaintext_template_sets_text_plain_header_and_subject()
    {
        $sawBefore = $sawLog = false;

        add_action('wpsms_email_before_send', function ($to, $subject, $message, $headers, $ctx) use (&$sawBefore) {
            $sawBefore = true;
            $this->assertSame('user@example.com', $to);
            $this->assertNotEmpty($subject);
            $this->assertIsArray($headers);
        }, 10, 5);

        add_action('wpsms_log_event', function ($event, $payload) use (&$sawLog) {
            if ($event === 'email_delivery_success') {
                $sawLog = true;
                $this->assertSame('otp_code', $payload['template']);
            }
        }, 10, 2);

        // Short-circuit actual mail send to "success"
        add_filter('pre_wp_mail', function () {
            return true;
        }, 10, 0);

        $channel = new EmailChannel();
        $ok      = $channel->send('user@example.com', '', [
            'template'           => EmailTemplate::TYPE_OTP_CODE,
            'otp_code'           => '654321',
            'expires_in_minutes' => '2',
            'user_display_name'  => 'Jane',
        ]);

        $this->assertTrue($ok);
        $this->assertTrue($sawBefore, 'Expected wpsms_email_before_send to fire');

        add_filter('pre_wp_mail', function ($short, $atts) {
            $headersJoined = implode("\n", (array)($atts['headers'] ?? []));
            $this->assertStringContainsStringIgnoringCase('Content-Type: text/plain', $headersJoined);
            return true;
        }, 11, 2);

        // Trigger once more to hit the header assertion
        $channel->send('user@example.com', '', [
            'template'           => EmailTemplate::TYPE_OTP_CODE,
            'otp_code'           => '123456',
            'expires_in_minutes' => '2',
            'user_display_name'  => 'Jane',
        ]);

        $this->assertTrue($sawLog, 'Expected success log event');
    }

    public function test_send_html_template_sets_text_html_header()
    {
        // Force the magic_link template body to be HTML
        add_filter('wpsms_email_template_body', function ($body, $id) {
            if ($id === EmailTemplate::TYPE_MAGIC_LINK) {
                return '<p>Click <a href="{{magic_link}}">here</a></p>';
            }
            return $body;
        }, 10, 2);

        // Short-circuit to success and assert headers
        add_filter('pre_wp_mail', function ($short, $atts) {
            $headersJoined = implode("\n", (array)($atts['headers'] ?? []));
            $this->assertStringContainsStringIgnoringCase('Content-Type: text/html', $headersJoined);
            return true;
        }, 10, 2);

        $channel = new EmailChannel();
        $ok      = $channel->send('u@example.com', '', [
            'template'           => EmailTemplate::TYPE_MAGIC_LINK,
            'magic_link'         => 'https://example.com/magic?t=1',
            'expires_in_minutes' => '15',
            'user_display_name'  => 'A',
        ]);

        $this->assertTrue($ok);
    }

    public function test_send_without_template_uses_fallback_subject_and_plain_content_type()
    {
        add_filter('pre_wp_mail', function ($short, $atts) {
            $headersJoined = implode("\n", (array)($atts['headers'] ?? []));
            $this->assertStringContainsStringIgnoringCase('Content-Type: text/plain', $headersJoined);
            $this->assertSame('Your Login info', $atts['subject'], 'Expected default subject when no template provided.');
            return true;
        }, 10, 2);

        $channel = new EmailChannel();
        $ok      = $channel->send('x@example.com', 'Body only, no template');

        $this->assertTrue($ok);
    }

    public function test_context_is_augmented_with_site_and_user_defaults()
    {
        // Replace body to expose the values that arrive to renderer
        add_filter('wpsms_email_template_body', function ($body, $id, $ctx) {
            $this->assertSame('Test Blog', $ctx['site_name'], 'site_name should be auto-filled');
            $this->assertSame('User', $ctx['user_display_name'], 'user_display_name default should be "User" when not provided');
            return "Hello {{user_display_name}} from {{site_name}}";
        }, 10, 3);

        add_filter('pre_wp_mail', function () {
            return true;
        }, 10, 0);

        $channel = new EmailChannel();
        $ok      = $channel->send('y@example.com', '', [
            'template'           => EmailTemplate::TYPE_OTP_CODE,
            // no user_display_name on purpose
            'otp_code'           => '111222',
            'expires_in_minutes' => '3',
        ]);

        $this->assertTrue($ok);
    }

    public function test_error_logging_on_failure()
    {
        $sawErrorLog = false;

        // Make sending fail
        add_filter('pre_wp_mail', function () {
            return false;
        }, 10, 0);

        add_action('wpsms_log_event', function ($event, $payload) use (&$sawErrorLog) {
            if ($event === 'email_delivery_error') {
                $sawErrorLog = true;
                $this->assertSame('raw', $payload['template'], 'No template provided should log "raw".');
                $this->assertNotEmpty($payload['error']);
            }
        }, 10, 2);

        $channel = new EmailChannel();
        $ok      = $channel->send('fail@example.com', 'x');

        $this->assertFalse($ok);
        $this->assertTrue($sawErrorLog, 'Expected error log event');
    }
}
