<?php

namespace WP_SMS\Services\OTP\Delivery\Email\Templating;

final class EmailTemplateRegistry
{
    /**
     * @return array
     */
    public static function all(): array
    {
        return [
            EmailTemplate::TYPE_OTP_CODE       => new EmailTemplate(
                EmailTemplate::TYPE_OTP_CODE,
                __('OTP Code', 'wp-sms'),
                ['{{otp_code}}', '{{expires_in_minutes}}', '{{user_display_name}}', '{{site_name}}'],
                __('Your login code for {{site_name}}', 'wp-sms'),
                __("Hi {{user_display_name}},\n\nYour one-time code is: {{otp_code}}\nThis code will expire in {{expires_in_minutes}} minutes.\n\nIf you didn’t request this, you can safely ignore this email.", 'wp-sms')
            ),
            EmailTemplate::TYPE_MAGIC_LINK     => new EmailTemplate(
                EmailTemplate::TYPE_MAGIC_LINK,
                __('Magic Link', 'wp-sms'),
                ['{{magic_link}}', '{{user_display_name}}', '{{expires_in_minutes}}', '{{site_name}}'],
                __('Secure Login to {{site_name}}', 'wp-sms'),
                __("Hi {{user_display_name}},\n\nClick the link below to log in:\n{{magic_link}}\n\nThis link expires in {{expires_in_minutes}} minutes.\nIf you didn’t request this, you can ignore this message.", 'wp-sms')
            ),
            EmailTemplate::TYPE_PASSWORD_RESET => new EmailTemplate(
                EmailTemplate::TYPE_PASSWORD_RESET,
                __('Password Reset', 'wp-sms'),
                ['{{reset_link}}', '{{user_display_name}}', '{{expires_in_minutes}}', '{{site_name}}'],
                __('Reset your password – {{site_name}}', 'wp-sms'),
                __("Hi {{user_display_name}},\n\nUse the link below to reset your password:\n{{reset_link}}\n\nThis link is valid for {{expires_in_minutes}} minutes.\nIf you didn’t request this, you can ignore this email.", 'wp-sms')
            ),
        ];
    }

    /**
     * @param string $id
     * @return EmailTemplate|null
     */
    public static function get(string $id): ?EmailTemplate
    {
        $all = self::all();
        return $all[$id] ?? null;
    }
}
