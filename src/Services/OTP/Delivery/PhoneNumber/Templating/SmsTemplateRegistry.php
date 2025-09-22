<?php

namespace WP_SMS\Services\OTP\Delivery\PhoneNumber\Templating;

final class SmsTemplateRegistry
{
    private static $templates = null;

    public static function all(): array
    {
        if (self::$templates === null) {
            self::$templates = [
                SmsTemplate::TYPE_OTP_CODE       => new SmsTemplate(
                    SmsTemplate::TYPE_OTP_CODE,
                    __('OTP Code (SMS)', 'wp-sms'),
                    ['{{otp_code}}', '{{expires_in_minutes}}', '{{user_display_name}}', '{{site_name}}'],
                    __("{{user_display_name}}, {{site_name}} code: {{otp_code}}. Expires in {{expires_in_minutes}} min. If this wasn't you, ignore.", 'wp-sms')),
                SmsTemplate::TYPE_MAGIC_LINK     => new SmsTemplate(
                    SmsTemplate::TYPE_MAGIC_LINK,
                    __('Magic Link (SMS)', 'wp-sms'),
                    ['{{magic_link}}', '{{expires_in_minutes}}', '{{user_display_name}}', '{{site_name}}'],
                    __("{{user_display_name}}, {{site_name}} login: {{magic_link}} (valid {{expires_in_minutes}} min).", 'wp-sms')
                ),
                SmsTemplate::TYPE_PASSWORD_RESET => new SmsTemplate(
                    SmsTemplate::TYPE_PASSWORD_RESET,
                    __('Password Reset (SMS)', 'wp-sms'),
                    ['{{user_display_name}}, {{reset_link}}', '{{expires_in_minutes}}', '{{user_display_name}}', '{{site_name}}'],
                    __("Reset {{site_name}} password: {{reset_link}} (valid {{expires_in_minutes}} min).", 'wp-sms')
                ),
                SmsTemplate::TYPE_COMBINED_REGISTER => new SmsTemplate(
                    SmsTemplate::TYPE_COMBINED_REGISTER,
                    __('Combined Registration (SMS)', 'wp-sms'),
                    ['{{otp_code}}', '{{magic_link}}', '{{expires_in_minutes}}', '{{user_display_name}}', '{{site_name}}'],
                    __("{{user_display_name}}, {{site_name}} registration: Code {{otp_code}} OR click {{magic_link}} (valid {{expires_in_minutes}} min).", 'wp-sms')
                ),
                SmsTemplate::TYPE_COMBINED_LOGIN => new SmsTemplate(
                    SmsTemplate::TYPE_COMBINED_LOGIN,
                    __('Combined Login (SMS)', 'wp-sms'),
                    ['{{otp_code}}', '{{magic_link}}', '{{expires_in_minutes}}', '{{user_display_name}}', '{{site_name}}'],
                    __("{{user_display_name}}, {{site_name}} login: Code {{otp_code}} OR click {{magic_link}} (valid {{expires_in_minutes}} min).", 'wp-sms')
                ),
            ];
        }

        return self::$templates;
    }

    public static function get(string $id): ?SmsTemplate
    {
        $all = self::all();
        return $all[$id] ?? null;
    }
}
