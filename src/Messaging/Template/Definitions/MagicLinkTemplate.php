<?php

namespace WSms\Messaging\Template\Definitions;

use WSms\Enums\TemplateType;
use WSms\Messaging\Template\Contracts\TemplateDefinitionInterface;
use WSms\Messaging\Template\ValueObjects\ChannelContent;
use WSms\Messaging\Template\ValueObjects\VariableDefinition;

defined('ABSPATH') || exit;

class MagicLinkTemplate implements TemplateDefinitionInterface
{
    public function getId(): string
    {
        return TemplateType::MagicLink->value;
    }

    public function getLabel(): string
    {
        return __('Magic Link', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Sent when a user requests a magic link to log in without a password.', 'wp-sms');
    }

    public function getVariables(): array
    {
        return [
            'magic_link_url' => new VariableDefinition(
                'magic_link_url',
                __('Magic Link URL', 'wp-sms'),
                __('The URL the user clicks to log in.', 'wp-sms'),
                true,
                'https://example.com/account/verify-magic-link?token=abc123',
            ),
            'expiry_minutes' => CommonVariables::expiryMinutes(),
            'site_name'      => CommonVariables::siteName(),
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
                body: __("Click the link below to log in:\nThis link expires in {{expiry_minutes}} minutes.\nIf you did not request this, please ignore this email.", 'wp-sms'),
                subject: __('[{{site_name}}] Your login link', 'wp-sms'),
                cta: __('Log in to {{site_name}}', 'wp-sms'),
                ctaUrl: '{{magic_link_url}}',
            ),
            'sms' => new ChannelContent(
                body: __('Log in to {{site_name}}: {{magic_link_url}} (expires in {{expiry_minutes}} min)', 'wp-sms'),
            ),
        ];
    }
}
