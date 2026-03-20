<?php

namespace WSms\Messaging\Template\Definitions;

use WSms\Enums\TemplateType;
use WSms\Messaging\Template\Contracts\TemplateDefinitionInterface;
use WSms\Messaging\Template\ValueObjects\ChannelContent;
use WSms\Messaging\Template\ValueObjects\VariableDefinition;

defined('ABSPATH') || exit;

class OtpWithMagicLinkTemplate implements TemplateDefinitionInterface
{
    public function getId(): string
    {
        return TemplateType::OtpWithMagicLink->value;
    }

    public function getLabel(): string
    {
        return __('OTP + Magic Link', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Sent when both OTP code and magic link are enabled for verification.', 'wp-sms');
    }

    public function getVariables(): array
    {
        return [
            'otp_code' => CommonVariables::otpCode(),
            'magic_link_url' => new VariableDefinition(
                'magic_link_url',
                __('Magic Link URL', 'wp-sms'),
                __('The URL the user clicks to log in.', 'wp-sms'),
                true,
                'https://example.com/account/verify-magic-link?token=abc123',
            ),
            'expiry_minutes' => CommonVariables::expiryMinutes(),
            'site_name'      => CommonVariables::siteName(),
            'ip_warning'     => CommonVariables::ipWarning(),
        ];
    }

    public function getSupportedChannels(): array
    {
        return ['email', 'sms'];
    }

    public function getDefaults(): array
    {
        return [
            'email' => new ChannelContent(
                body: __("Your verification code is:\n<p style=\"font-size:24px;font-weight:bold;letter-spacing:4px;\">{{otp_code}}</p>\nOr click the button below to log in directly.\nThis expires in {{expiry_minutes}} minutes.\n{{ip_warning}}", 'wp-sms'),
                subject: __('[{{site_name}}] Your verification code', 'wp-sms'),
                cta: __('Log in to {{site_name}}', 'wp-sms'),
                ctaUrl: '{{magic_link_url}}',
            ),
            'sms' => new ChannelContent(
                body: __('Your code: {{otp_code}}. Or log in: {{magic_link_url}} (expires in {{expiry_minutes}} min)', 'wp-sms'),
            ),
        ];
    }
}
