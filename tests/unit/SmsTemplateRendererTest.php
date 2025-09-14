<?php

namespace unit;

use WP_UnitTestCase;
use WP_SMS\Services\OTP\Delivery\PhoneNumber\Templating\TemplateRenderer;
use WP_SMS\Services\OTP\Delivery\PhoneNumber\Templating\SmsTemplate;
use WP_SMS\Services\OTP\Delivery\PhoneNumber\Templating\SmsTemplateStorage;

class SmsTemplateRendererTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        update_option('blogname', 'Test Blog');
    }

    public function test_uses_default_body_from_definition()
    {
        $renderer = new TemplateRenderer();

        $out = $renderer->render(SmsTemplate::TYPE_OTP_CODE, [
            'otp_code'           => 123456,
            'expires_in_minutes' => '2',
            'user_display_name'  => 'Jane',
            'site_name'          => 'My Site',
        ]);

        $this->assertNotEmpty($out['body']);
        $this->assertStringContainsString('123456', $out['body']);
        $this->assertStringContainsString('2', $out['body']);
        $this->assertStringContainsString('Jane', $out['body']);
    }

    public function test_placeholders_are_allow_listed_and_safely_escaped()
    {
        $renderer = new TemplateRenderer();

        $out = $renderer->render(SmsTemplate::TYPE_OTP_CODE, [
            'otp_code'           => '<script>alert(1)</script>',
            'expires_in_minutes' => '<b>5</b>',
            'user_display_name'  => 'Alice & Bob',
            'not_allowed'        => '{{not_allowed}}',
        ]);

        // no raw tags/scripts
        $this->assertStringNotContainsString('<script>', $out['body']);
        $this->assertStringNotContainsString('<b>5</b>', $out['body']);
        // entities should be escaped to plain text
        $this->assertStringContainsString('Alice & Bob', $out['body']); // wp_strip_all_tags keeps '&' as-is in plain text
        // disallowed token should not appear unless present in the template (it isn't)
        $this->assertStringNotContainsString('{{not_allowed}}', $out['body']);
    }

    public function test_link_placeholders_are_sanitized_as_urls_not_html()
    {
        // Force a template body that contains {{magic_link}} to check URL sanitization
        add_filter('wpsms_sms_template_body', function ($body, $id) {
            if ($id === SmsTemplate::TYPE_MAGIC_LINK) {
                return 'Login: {{magic_link}}';
            }
            return $body;
        }, 10, 2);

        $renderer = new TemplateRenderer();

        $out = $renderer->render(SmsTemplate::TYPE_MAGIC_LINK, [
            'magic_link'         => 'javascript:alert("x")', // should be stripped
            'expires_in_minutes' => '10',
            'user_display_name'  => 'Jane',
            'site_name'          => 'Site',
        ]);

        $this->assertStringNotContainsString('javascript:', $out['body']);
        $this->assertMatchesRegularExpression('/Login:\s*\S*/', $out['body']);
    }

    public function test_custom_storage_overrides_default_and_revert_disables_custom()
    {
        // Simulate custom templates saved via options keys that SmsTemplateStorage::map() expects
        \WP_SMS\Option::updateOption('sms_tpl_otp_body', 'Custom SMS for {{user_display_name}} code {{otp_code}} @ {{site_name}}');
        \WP_SMS\Option::updateOption('sms_tpl_otp_revert', 0);

        $renderer = new TemplateRenderer();

        $out = $renderer->render(SmsTemplate::TYPE_OTP_CODE, [
            'otp_code'           => '999999',
            'user_display_name'  => 'Zoe',
            'site_name'          => 'Test Blog',
            'expires_in_minutes' => '3',
        ]);

        $this->assertStringContainsString('Custom SMS for Zoe code 999999 @ Test Blog', $out['body']);

        // Now set revert flag; custom body should be ignored in favor of defaults
        \WP_SMS\Option::updateOption('sms_tpl_otp_revert', 1);

        $out2 = $renderer->render(SmsTemplate::TYPE_OTP_CODE, [
            'otp_code'           => '111222',
            'user_display_name'  => 'Zoe',
            'site_name'          => 'Test Blog',
            'expires_in_minutes' => '3',
        ]);

        $this->assertStringNotContainsString('Custom SMS for', $out2['body']);
    }

    public function test_unknown_template_id_falls_back_to_otp_code()
    {
        $renderer = new TemplateRenderer();

        $out = $renderer->render('does_not_exist', [
            'otp_code'           => '123123',
            'user_display_name'  => 'U',
            'site_name'          => 'S',
            'expires_in_minutes' => '1',
        ]);

        $this->assertNotEmpty($out['body']);
        $this->assertStringContainsString('123123', $out['body']);
    }
}
