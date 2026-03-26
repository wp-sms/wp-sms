<?php

namespace WSms\Messaging\Template\Definitions;

use WSms\Enums\TemplateType;
use WSms\Messaging\Template\Contracts\TemplateDefinitionInterface;
use WSms\Messaging\Template\ValueObjects\ChannelContent;
defined('ABSPATH') || exit;

class OtpTemplate implements TemplateDefinitionInterface
{
    public function getId(): string
    {
        return TemplateType::Otp->value;
    }

    public function getLabel(): string
    {
        return __('OTP Verification Code', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Sent when a user needs to verify their identity with a one-time code.', 'wp-sms');
    }

    public function getVariables(): array
    {
        return [
            'otp_code'       => CommonVariables::otpCode(),
            'expiry_minutes' => CommonVariables::expiryMinutes(),
            'site_name'      => CommonVariables::siteName(),
            'ip_warning'     => CommonVariables::ipWarning(),
        ];
    }

    public function getSupportedChannels(): array
    {
        return ['email', 'sms', 'whatsapp', 'telegram', 'rcs'];
    }

    public function getDefaults(): array
    {
        return [
            'email' => new ChannelContent(
                body: __("Your verification code is:\n<p style=\"font-size:24px;font-weight:bold;letter-spacing:4px;\">{{otp_code}}</p>\nThis code expires in {{expiry_minutes}} minutes.\n{{ip_warning}}", 'wp-sms'),
                subject: __('[{{site_name}}] Your verification code', 'wp-sms'),
            ),
            'sms' => new ChannelContent(
                body: __('Your verification code is: {{otp_code}}. Expires in {{expiry_minutes}} min.', 'wp-sms'),
            ),
            'whatsapp' => new ChannelContent(
                body: __('Your verification code is: {{otp_code}}. Expires in {{expiry_minutes}} min.', 'wp-sms'),
            ),
            'telegram' => new ChannelContent(
                body: __('<b>Your verification code is: {{otp_code}}</b>' . "\n" . 'It expires in {{expiry_minutes}} minutes.', 'wp-sms'),
            ),
            'rcs' => new ChannelContent(
                body: __('Your verification code is: {{otp_code}}. Expires in {{expiry_minutes}} min.', 'wp-sms'),
            ),
        ];
    }
}
