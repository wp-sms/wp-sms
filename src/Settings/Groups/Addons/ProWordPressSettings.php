<?php

namespace WP_SMS\Settings\Groups\Addons;

use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\Field;

class ProWordPressSettings extends AbstractSettingGroup
{
    public function getName(): string
    {
        return 'pro_wordpress';
    }

    public function getLabel(): string
    {
        return 'Pro WordPress (Login & OTP)';
    }

    public function getFields(): array
    {
        return [
            new Field([
                'key'         => 'login_title',
                'type'        => 'header',
                'label'       => 'Login With SMS',
                'description' => 'Section heading for login via SMS',
                'group_label' => 'Login',
            ]),
            new Field([
                'key'         => 'login_sms',
                'type'        => 'checkbox',
                'label'       => 'Status',
                'description' => 'Allows users to log in with a verification code sent via SMS.',
                'group_label' => 'Login',
            ]),
            new Field([
                'key'         => 'login_sms_message',
                'type'        => 'textarea',
                'label'       => 'Message body',
                'description' => 'SMS message format for login. Placeholders: <code>%code%</code>, <code>%user_name%</code>, <code>%full_name%</code>, <code>%site_name%</code>, <code>%site_url%</code>',
                'group_label' => 'Login',
            ]),
            new Field([
                'key'         => 'register_sms',
                'type'        => 'checkbox',
                'label'       => 'User Account Creation on Login',
                'description' => 'Automatically register user if logging in via SMS and account does not exist.',
                'group_label' => 'Login',
            ]),
            new Field([
                'key'         => 'otp_title',
                'type'        => 'header',
                'label'       => 'Two-Factor Authentication with SMS',
                'description' => 'Section heading for 2FA',
                'group_label' => 'OTP',
            ]),
            new Field([
                'key'         => 'mobile_verify',
                'type'        => 'checkbox',
                'label'       => 'Status',
                'description' => 'Enable SMS verification as part of the login process.',
                'group_label' => 'OTP',
            ]),
            new Field([
                'key'         => 'mobile_verify_method',
                'type'        => 'select',
                'label'       => 'Authentication Policy',
                'description' => 'Choose whether 2FA is optional or enforced for all users.',
                'options'     => [
                    'optional'  => 'Optional - Users can enable/disable it in their profile',
                    'force_all' => 'Enable for All Users',
                ],
                'group_label' => 'OTP',
            ]),
            new Field([
                'key'         => 'mobile_verify_message',
                'type'        => 'textarea',
                'label'       => 'Message Content',
                'description' => 'SMS format for 2FA. Placeholders: <code>%otp%</code>, <code>%user_name%</code>, <code>%first_name%</code>, <code>%last_name%</code>',
                'group_label' => 'OTP',
            ]),
        ];
    }

    public function getOptionKeyName(): ?string
    {
        return 'pro';
    }
}
