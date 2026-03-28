<?php

namespace WSms\Messaging\Template\Definitions;

use WSms\Enums\TemplateType;
use WSms\Messaging\Template\Contracts\ToggleableTemplateInterface;
use WSms\Messaging\Template\ValueObjects\ChannelContent;
use WSms\Messaging\Template\ValueObjects\VariableDefinition;

defined('ABSPATH') || exit;

class AccountAlertTemplate implements ToggleableTemplateInterface
{
    public function getId(): string
    {
        return TemplateType::AccountAlert->value;
    }

    public function getLabel(): string
    {
        return __('Account Alert', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Sent for security-related account notifications (e.g. password changed, new login).', 'wp-sms');
    }

    public function getVariables(): array
    {
        return [
            'alert_message' => new VariableDefinition(
                'alert_message',
                __('Alert Message', 'wp-sms'),
                __('The security alert message content.', 'wp-sms'),
                true,
                'Your password was recently changed.',
            ),
            'site_name'        => CommonVariables::siteName(),
            'security_context' => CommonVariables::securityContext(),
        ];
    }

    public function getSupportedChannels(): array
    {
        return ['email', 'sms', 'telegram'];
    }

    public function getDefaults(): array
    {
        return [
            'email' => new ChannelContent(
                body: __("{{alert_message}}\n{{security_context}}", 'wp-sms'),
                subject: __('[{{site_name}}] Security Alert', 'wp-sms'),
            ),
            'sms' => new ChannelContent(
                body: __('[{{site_name}}] {{alert_message}}', 'wp-sms'),
            ),
            'telegram' => new ChannelContent(
                body: __('<b>[{{site_name}}] Security Alert</b>' . "\n" . '{{alert_message}}', 'wp-sms'),
            ),
        ];
    }

    public function isEnabledByDefault(): bool
    {
        return false;
    }
}
