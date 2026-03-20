<?php

namespace WSms\Messaging\Template\Definitions;

use WSms\Enums\TemplateType;
use WSms\Messaging\Template\Contracts\TemplateDefinitionInterface;
use WSms\Messaging\Template\ValueObjects\ChannelContent;
use WSms\Messaging\Template\ValueObjects\VariableDefinition;

defined('ABSPATH') || exit;

class EmailVerificationTemplate implements TemplateDefinitionInterface
{
    public function getId(): string
    {
        return TemplateType::EmailVerification->value;
    }

    public function getLabel(): string
    {
        return __('Email Verification', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Sent when a user needs to verify their email address via a link.', 'wp-sms');
    }

    public function getVariables(): array
    {
        return [
            'verify_url' => new VariableDefinition(
                'verify_url',
                __('Verification URL', 'wp-sms'),
                __('The URL the user clicks to verify their email.', 'wp-sms'),
                true,
                'https://example.com/account/verify-email?token=abc123',
            ),
            'expiry_minutes' => CommonVariables::expiryMinutes('60'),
            'site_name'      => CommonVariables::siteName(),
        ];
    }

    public function getSupportedChannels(): array
    {
        return ['email'];
    }

    public function getDefaults(): array
    {
        return [
            'email' => new ChannelContent(
                body: __("Please verify your email address by clicking the button below.\nThis link expires in {{expiry_minutes}} minutes.\nIf you did not create an account, please ignore this email.", 'wp-sms'),
                subject: __('[{{site_name}}] Verify your email address', 'wp-sms'),
                cta: __('Verify Email', 'wp-sms'),
                ctaUrl: '{{verify_url}}',
            ),
        ];
    }
}
