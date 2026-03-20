<?php

namespace WSms\Messaging\Template\Definitions;

use WSms\Enums\TemplateType;
use WSms\Messaging\Template\Contracts\TemplateDefinitionInterface;
use WSms\Messaging\Template\ValueObjects\ChannelContent;

defined('ABSPATH') || exit;

class PhoneVerificationTemplate implements TemplateDefinitionInterface
{
    public function getId(): string
    {
        return TemplateType::PhoneVerification->value;
    }

    public function getLabel(): string
    {
        return __('Phone Verification', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Sent when a user needs to verify their phone number with an OTP code.', 'wp-sms');
    }

    public function getVariables(): array
    {
        return [
            'otp_code'       => CommonVariables::otpCode(),
            'expiry_minutes' => CommonVariables::expiryMinutes('5'),
            'site_name'      => CommonVariables::siteName(),
        ];
    }

    public function getSupportedChannels(): array
    {
        return ['sms', 'whatsapp', 'telegram'];
    }

    public function getDefaults(): array
    {
        return [
            'sms' => new ChannelContent(
                body: __('Your verification code is: {{otp_code}}. Expires in {{expiry_minutes}} min.', 'wp-sms'),
            ),
            'whatsapp' => new ChannelContent(
                body: __('Your verification code is: {{otp_code}}. Expires in {{expiry_minutes}} min.', 'wp-sms'),
            ),
            'telegram' => new ChannelContent(
                body: __('<b>Your verification code is: {{otp_code}}</b>' . "\n" . 'It expires in {{expiry_minutes}} minutes.', 'wp-sms'),
            ),
        ];
    }
}
