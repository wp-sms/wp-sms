<?php

namespace WSms\Messaging\Template\Definitions;

use WSms\Enums\TemplateType;
use WSms\Messaging\Template\Contracts\ToggleableTemplateInterface;
use WSms\Messaging\Template\ValueObjects\ChannelContent;

defined('ABSPATH') || exit;

class AccountSuspendedTemplate implements ToggleableTemplateInterface
{
    public function getId(): string
    {
        return TemplateType::AccountSuspended->value;
    }

    public function getLabel(): string
    {
        return __('Account Suspended', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Sent when an administrator suspends a user account.', 'wp-sms');
    }

    public function getVariables(): array
    {
        return [
            'user_name' => CommonVariables::userName(),
            'site_name' => CommonVariables::siteName(),
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
                body: __("Your account on {{site_name}} has been suspended.\nIf you believe this is a mistake, please contact the site administrator.", 'wp-sms'),
                subject: __('[{{site_name}}] Account Suspended', 'wp-sms'),
            ),
        ];
    }

    public function isEnabledByDefault(): bool
    {
        return false;
    }
}
