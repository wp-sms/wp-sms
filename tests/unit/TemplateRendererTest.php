<?php

namespace unit;

use WP_UnitTestCase;
use WP_SMS\Services\OTP\Delivery\Email\Templating\TemplateRenderer;
use WP_SMS\Services\OTP\Delivery\Email\Templating\EmailTemplateRegistry;
use WP_SMS\Services\OTP\Delivery\Email\Templating\EmailTemplateStorage;

class TemplateRendererTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        update_option('blogname', 'Test Blog');
    }

    public function test_uses_default_subject_and_body_from_definition()
    {
        $renderer = new TemplateRenderer();
        $out      = $renderer->render('otp_code', [
            'otp_code'           => 123456,
            'expires_in_minutes' => '2',
            'user_display_name'  => 'Jane',
            'site_name'          => 'My Site',
        ]);

        $this->assertNotEmpty($out['subject'], 'Subject should be populated from default template.');
        $this->assertNotEmpty($out['body'], 'Body should be populated from default template.');
        $this->assertIsBool($out['is_html']);
        $this->assertFalse($out['is_html'], 'Default OTP body is plain text.');
        $this->assertStringContainsString('123456', $out['body']);
        $this->assertStringContainsString('2', $out['body']);
        $this->assertStringContainsString('Jane', $out['body']);
    }

    public function test_placeholders_are_escaped_and_allow_listed()
    {
        $renderer = new TemplateRenderer();

        // Attempt to inject disallowed placeholders and HTML
        $out = $renderer->render('otp_code', [
            'otp_code'           => '<script>alert(1)</script>',
            'expires_in_minutes' => '<b>5</b>',
            'user_display_name'  => 'Alice & Bob',
            // disallowed token that's not in the template's allowed placeholders
            'not_allowed'        => '{{not_allowed}}',
        ]);

        // Must be escaped (no <script> in output)
        $this->assertStringNotContainsString('<script>', $out['body']);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $out['body']);

        // HTML in numeric should be escaped too
        $this->assertStringNotContainsString('<b>5</b>', $out['body']);
        $this->assertStringContainsString('&lt;b&gt;5&lt;/b&gt;', $out['body']);

        // Ampersand escaped
        $this->assertStringContainsString('Alice &amp; Bob', $out['body']);

        // Disallowed placeholder string should remain literally present if part of template text (it isn’t), or be ignored if from context
        $this->assertStringNotContainsString('{{not_allowed}}', $out['body']);
    }

    public function test_link_placeholders_are_escaped_as_urls_not_html()
    {
        // For magic_link template we’ll include a raw template with anchor to ensure *_link is URL-escaped
        add_filter('wpsms_email_template_body', function ($body, $id, $ctx) {
            if ($id === 'magic_link') {
                return 'Click <a href="{{magic_link}}">here</a>';
            }
            return $body;
        }, 10, 3);

        $renderer = new TemplateRenderer();

        $out = $renderer->render('magic_link', [
            'magic_link'         => 'javascript:alert("x")', // nasty
            'expires_in_minutes' => '10',
            'user_display_name'  => 'Jane',
            'site_name'          => 'Site',
        ]);

        // Should be treated as a URL, so `esc_url` strips javascript: scheme
        $this->assertStringNotContainsString('javascript:', $out['body']);
        $this->assertMatchesRegularExpression('/href="[^"]*"/', $out['body']);
        $this->assertTrue($out['is_html'], 'HTML body should be detected.');
    }

    public function test_custom_storage_overrides_default_and_revert_disables_custom()
    {
        // Simulate custom template stored via options keys
        \WP_SMS\Option::updateOption('email_tpl_otp_subject', 'Custom Subject {{site_name}}');
        \WP_SMS\Option::updateOption('email_tpl_otp_body', 'Custom Body for {{user_display_name}} code {{otp_code}}');
        \WP_SMS\Option::updateOption('email_tpl_otp_revert', 0);

        $renderer = new TemplateRenderer();
        $out      = $renderer->render('otp_code', [
            'otp_code'           => '999999',
            'user_display_name'  => 'Zoe',
            'site_name'          => 'Test Blog',
            'expires_in_minutes' => '3',
        ]);

        $this->assertSame('Custom Subject Test Blog', $out['subject']);
        $this->assertStringContainsString('Custom Body for Zoe code 999999', $out['body']);

        // Now flip revert flag; should ignore custom and use defaults
        \WP_SMS\Option::updateOption('email_tpl_otp_revert', 1);

        $out2 = $renderer->render('otp_code', [
            'otp_code'           => '111222',
            'user_display_name'  => 'Zoe',
            'site_name'          => 'Test Blog',
            'expires_in_minutes' => '3',
        ]);

        $this->assertNotSame('Custom Subject Test Blog', $out2['subject']);
        $this->assertStringNotContainsString('Custom Body for', $out2['body']);
    }

    public function test_unknown_template_id_falls_back_to_otp_code()
    {
        $renderer = new TemplateRenderer();
        $out      = $renderer->render('does_not_exist', [
            'otp_code'           => '123123',
            'user_display_name'  => 'U',
            'site_name'          => 'S',
            'expires_in_minutes' => '1',
        ]);

        $this->assertNotEmpty($out['subject']);
        $this->assertStringContainsString('123123', $out['body']);
    }
}
