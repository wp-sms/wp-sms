<?php

namespace WP_SMS\Settings\Groups\Addons;

use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\Field;
use WP_SMS\Settings\Section;
use WP_SMS\Settings\LucideIcons;
use WP_SMS\Settings\Tags;

class ProWordPressSettings extends AbstractSettingGroup
{
    public function getName(): string
    {
        return 'pro_wordpress';
    }

    public function getLabel(): string
    {
        return __('Pro WordPress (Login & OTP)', 'wp-sms');
    }

    public function getIcon(): string
    {
        return LucideIcons::SHIELD;
    }

    public function getSections(): array
    {
        return [
            new Section([
                'id' => 'login_with_sms',
                'title' => __('Login With SMS', 'wp-sms'),
                'subtitle' => __('Allow users to log in with a verification code sent via SMS.', 'wp-sms'),
                'tag' => !$this->proIsInstalled() ? Tags::PRO : null,
                'fields' => [
                    new Field([
                        'key'         => 'login_sms',
                        'type'        => 'checkbox',
                        'label'       => __('Status', 'wp-sms'),
                        'description' => __('Allows users to log in with a verification code sent via SMS.', 'wp-sms'),
                        'readonly' => !$this->proIsInstalled()
                    ]),
                    new Field([
                        'key'         => 'login_sms_message',
                        'type'        => 'textarea',
                        'label'       => __('Message body', 'wp-sms'),
                        'show_if' => ['login_sms' => true],
                        'description' => __('SMS message format for login. Placeholders: <code>%code%</code>, <code>%user_name%</code>, <code>%full_name%</code>, <code>%site_name%</code>, <code>%site_url%</code>', 'wp-sms'),
                        'readonly' => !$this->proIsInstalled()
                    ]),
                    new Field([
                        'key'         => 'register_sms',
                        'type'        => 'checkbox',
                        'label'       => __('User Account Creation on Login', 'wp-sms'),
                        'description' => __('Automatically register user if logging in via SMS and account does not exist.', 'wp-sms'),
                        'show_if' => ['login_sms' => true],
                        'readonly' => !$this->proIsInstalled()
                    ]),
                ]
            ]),
            new Section([
                'id' => 'two_factor_authentication',
                'title' => __('Two-Factor Authentication with SMS', 'wp-sms'),
                'subtitle' => __('Enable SMS verification as part of the login process.', 'wp-sms'),
                'tag' => !$this->proIsInstalled() ? Tags::PRO : null,
                'fields' => [
                    new Field([
                        'key'         => 'mobile_verify',
                        'type'        => 'checkbox',
                        'label'       => __('Status', 'wp-sms'),
                        'description' => __('Enable SMS verification as part of the login process.', 'wp-sms'),
                        'readonly' => !$this->proIsInstalled()
                    ]),
                    new Field([
                        'key'         => 'mobile_verify_method',
                        'type'        => 'select',
                        'label'       => __('Authentication Policy', 'wp-sms'),
                        'description' => __('Choose whether 2FA is optional or enforced for all users.', 'wp-sms'),
                        'show_if' => ['mobile_verify' => true],
                        'options'     => [
                            'optional'  => __('Optional - Users can enable/disable it in their profile', 'wp-sms'),
                            'force_all' => __('Enable for All Users', 'wp-sms'),
                        ],
                        'readonly' => !$this->proIsInstalled()
                    ]),
                    new Field([
                        'key'         => 'mobile_verify_message',
                        'type'        => 'textarea',
                        'label'       => __('Message Content', 'wp-sms'),
                        'description' => __('SMS format for 2FA. Placeholders: <code>%otp%</code>, <code>%user_name%</code>, <code>%first_name%</code>, <code>%last_name%</code>', 'wp-sms'),
                        'show_if' => ['mobile_verify' => true],
                        'readonly' => !$this->proIsInstalled()
                    ]),
                ]
            ]),
        ];
    }

    public function getMetaData(){
        return [
            'addon' => 'pro',
        ];
    }

    public function getFields(): array
    {
        // Legacy method - return all fields from all sections for backward compatibility
        $allFields = [];
        foreach ($this->getSections() as $section) {
            $allFields = array_merge($allFields, $section->getFields());
        }
        return $allFields;
    }


    public function getOptionKeyName(): ?string
    {
        return 'pro';
    }
}
