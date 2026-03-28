<?php

namespace WSms\Messaging\Template\Definitions;

use WSms\Enums\TemplateType;
use WSms\Messaging\Template\Contracts\TemplateDefinitionInterface;
use WSms\Messaging\Template\ValueObjects\ChannelContent;
use WSms\Messaging\Template\ValueObjects\VariableDefinition;

defined('ABSPATH') || exit;

class PasswordResetTemplate implements TemplateDefinitionInterface
{
    public function getId(): string
    {
        return TemplateType::PasswordReset->value;
    }

    public function getLabel(): string
    {
        return __('Password Reset', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Sent when a user requests to reset their password.', 'wp-sms');
    }

    public function getVariables(): array
    {
        return [
            'reset_url' => new VariableDefinition(
                'reset_url',
                __('Reset URL', 'wp-sms'),
                __('The URL the user clicks to reset their password.', 'wp-sms'),
                true,
                'https://example.com/account/reset-password?token=abc123',
            ),
            'expiry_minutes' => CommonVariables::expiryMinutes('60'),
            'site_name'      => CommonVariables::siteName(),
            'security_context' => CommonVariables::securityContext(),
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
                body: __("Click the button below to reset your password.\nThis link expires in {{expiry_minutes}} minutes.\n{{security_context}}", 'wp-sms'),
                subject: __('[{{site_name}}] Reset your password', 'wp-sms'),
                cta: __('Reset Password', 'wp-sms'),
                ctaUrl: '{{reset_url}}',
            ),
        ];
    }
}
