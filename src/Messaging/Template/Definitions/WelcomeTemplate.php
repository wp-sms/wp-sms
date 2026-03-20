<?php

namespace WSms\Messaging\Template\Definitions;

use WSms\Enums\TemplateType;
use WSms\Messaging\Template\Contracts\TemplateDefinitionInterface;
use WSms\Messaging\Template\ValueObjects\ChannelContent;
use WSms\Messaging\Template\ValueObjects\VariableDefinition;

defined('ABSPATH') || exit;

class WelcomeTemplate implements TemplateDefinitionInterface
{
    public function getId(): string
    {
        return TemplateType::Welcome->value;
    }

    public function getLabel(): string
    {
        return __('Welcome Message', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Sent to a user after successful registration.', 'wp-sms');
    }

    public function getVariables(): array
    {
        return [
            'user_name' => new VariableDefinition(
                'user_name',
                __('User Name', 'wp-sms'),
                __('The display name of the registered user.', 'wp-sms'),
                false,
                'John',
            ),
            'site_name' => CommonVariables::siteName(),
            'login_url' => new VariableDefinition(
                'login_url',
                __('Login URL', 'wp-sms'),
                __('The URL where the user can log in.', 'wp-sms'),
                false,
                'https://example.com/account/login',
            ),
        ];
    }

    public function getSupportedChannels(): array
    {
        return ['email', 'sms', 'whatsapp', 'telegram'];
    }

    public function getDefaults(): array
    {
        return [
            'email' => new ChannelContent(
                body: __("Welcome to {{site_name}}, {{user_name}}!\nYour account has been created successfully. You can now log in to access your account.", 'wp-sms'),
                subject: __('Welcome to {{site_name}}!', 'wp-sms'),
                cta: __('Log In', 'wp-sms'),
                ctaUrl: '{{login_url}}',
            ),
            'sms' => new ChannelContent(
                body: __('Welcome to {{site_name}}, {{user_name}}! Your account is ready.', 'wp-sms'),
            ),
            'whatsapp' => new ChannelContent(
                body: __('Welcome to {{site_name}}, {{user_name}}! Your account is ready.', 'wp-sms'),
            ),
            'telegram' => new ChannelContent(
                body: __('<b>Welcome to {{site_name}}, {{user_name}}!</b>' . "\n" . 'Your account is ready.', 'wp-sms'),
            ),
        ];
    }
}
