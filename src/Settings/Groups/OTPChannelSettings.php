<?php

namespace WP_SMS\Settings\Groups;

use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\Field;
use WP_SMS\Settings\Section;
use WP_SMS\Settings\LucideIcons;
use WP_SMS\Settings\Tags;

class OTPChannelSettings extends AbstractSettingGroup
{
    public function getName(): string
    {
        return 'otp-channel';
    }

    public function getLabel(): string
    {
        return __('OTP Channels', 'wp-sms');
    }

    public function getIcon(): string
    {
        return LucideIcons::SHIELD;
    }

    /**
     * Hide OTP Channels from API endpoints by default.
     * This group contains sensitive OTP configuration that should not be exposed.
     *
     * @return bool
     */
    public function isApiVisible(): bool
    {
        // TODO: Hide OTP Channels from API endpoints by default.
        // This group contains sensitive OTP configuration that should not be exposed.
        return false;
    }

    public function getSections(): array
    {
        return [
            new Section([
                'id'       => 'otp_channels',
                'title'    => __('OTP Channels', 'wp-sms'),
                'subtitle' => __('Configure OTP verification channels and their settings', 'wp-sms'),
                'order'    => 2,
                'fields'   => [
                    // Phone Channel
                    new Field([
                        'key'        => 'otp_channel_phone',
                        'label'      => __('Phone Number', 'wp-sms'),
                        'type'       => 'checkbox',
                        'default'    => false,
                        'sub_fields' => [
                            new Field([
                                'key'         => 'otp_channel_phone_verification_method',
                                'label'       => __('Verification Method', 'wp-sms'),
                                'type'        => 'multiselect',
                                'description' => __('Select verification methods for phone channel', 'wp-sms'),
                                'options'     => [
                                    'otp'  => __('OTP Code', 'wp-sms'),
                                    'link' => __('Verification Link', 'wp-sms'),
                                ],
                                'default'     => ['otp'],
                            ]),
                            new Field([
                                'key'         => 'otp_channel_phone_otp_digits',
                                'label'       => __('OTP Digits', 'wp-sms'),
                                'type'        => 'number',
                                'description' => __('Number of digits in OTP codes for phone verification', 'wp-sms'),
                                'default'     => 6,
                                'min'         => 4,
                                'max'         => 8,
                            ]),
                            new Field([
                                'key'         => 'otp_channel_phone_expiry_seconds',
                                'label'       => __('OTP Expiry (seconds)', 'wp-sms'),
                                'type'        => 'number',
                                'description' => __('Time in seconds before phone OTP codes expire', 'wp-sms'),
                                'default'     => 300,
                                'min'         => 60,
                                'max'         => 1800,
                                'step'        => 30,
                            ]),
                            new Field([
                                'key'         => 'otp_channel_phone_sms',
                                'label'       => __('Enable SMS Delivery', 'wp-sms'),
                                'type'        => 'checkbox',
                                'description' => __('Allow OTP delivery via SMS', 'wp-sms'),
                                'default'     => true,
                            ]),
                            new Field([
                                'key'         => 'otp_channel_phone_whatsapp',
                                'label'       => __('Enable WhatsApp Delivery', 'wp-sms'),
                                'type'        => 'checkbox',
                                'description' => __('Allow OTP delivery via WhatsApp', 'wp-sms'),
                                'default'     => false,
                                'readonly'    => true,
                                'tag'         => Tags::COMING_SOON,
                            ]),
                            new Field([
                                'key'         => 'otp_channel_phone_viber',
                                'label'       => __('Enable Viber Delivery', 'wp-sms'),
                                'type'        => 'checkbox',
                                'description' => __('Allow OTP delivery via Viber', 'wp-sms'),
                                'default'     => false,
                                'readonly'    => true,
                                'tag'         => Tags::COMING_SOON,
                            ]),
                            new Field([
                                'key'         => 'otp_channel_phone_call',
                                'label'       => __('Enable Phone Call Delivery', 'wp-sms'),
                                'type'        => 'checkbox',
                                'description' => __('Allow OTP delivery via phone call', 'wp-sms'),
                                'default'     => false,
                                'readonly'    => true,
                                'tag'         => Tags::COMING_SOON,
                            ]),

                            new Field([
                                'key'         => 'otp_channel_phone_country_code',
                                'label'       => __('Country Code Handling', 'wp-sms'),
                                'type'        => 'checkbox',
                                'description' => __('Automatically add country codes to phone numbers', 'wp-sms'),
                                'default'     => true,
                                'readonly'    => true,
                                'tag'         => Tags::COMING_SOON,
                            ]),
                            new Field([
                                'key'         => 'otp_channel_phone_required_signup',
                                'label'       => __('Required at Sign Up', 'wp-sms'),
                                'type'        => 'checkbox',
                                'description' => __('Phone number is required during user registration', 'wp-sms'),
                                'default'     => false,
                            ]),
                            new Field([
                                'key'         => 'otp_channel_phone_verify_signup',
                                'label'       => __('Verify at Sign Up', 'wp-sms'),
                                'type'        => 'checkbox',
                                'description' => __('Phone number must be verified during registration', 'wp-sms'),
                                'default'     => true,
                            ]),
                            new Field([
                                'key'         => 'otp_channel_phone_allow_signin',
                                'label'       => __('Allow to Sign In', 'wp-sms'),
                                'type'        => 'checkbox',
                                'description' => __('Allow users to sign in using phone number', 'wp-sms'),
                                'default'     => true,
                            ])
                        ]
                    ]),
                    // Email Channel
                    new Field([
                        'key'        => 'otp_channel_email',
                        'label'      => __('Email', 'wp-sms'),
                        'type'       => 'checkbox',
                        'default'    => false,
                        'sub_fields' => [
                            new Field([
                                'key'         => 'otp_channel_email_verification_method',
                                'label'       => __('Verification Method', 'wp-sms'),
                                'type'        => 'multiselect',
                                'description' => __('Select verification methods for email channel', 'wp-sms'),
                                'options'     => [
                                    'otp'  => __('OTP Code', 'wp-sms'),
                                    'link' => __('Verification Link', 'wp-sms'),
                                ],
                                'default'     => ['otp'],
                            ]),
                            new Field([
                                'key'         => 'otp_channel_email_otp_digits',
                                'label'       => __('OTP Digits', 'wp-sms'),
                                'type'        => 'number',
                                'description' => __('Number of digits in OTP codes for email verification', 'wp-sms'),
                                'default'     => 6,
                                'min'         => 4,
                                'max'         => 8,
                            ]),
                            new Field([
                                'key'         => 'otp_channel_email_expiry_seconds',
                                'label'       => __('OTP Expiry (seconds)', 'wp-sms'),
                                'type'        => 'number',
                                'description' => __('Time in seconds before email OTP codes expire', 'wp-sms'),
                                'default'     => 300,
                                'min'         => 60,
                                'max'         => 1800,
                                'step'        => 30,
                            ]),
                            new Field([
                                'key'         => 'otp_channel_email_required_signup',
                                'label'       => __('Required at Sign Up', 'wp-sms'),
                                'type'        => 'checkbox',
                                'description' => __('Email is required during user registration', 'wp-sms'),
                                'default'     => false,
                            ]),
                            new Field([
                                'key'         => 'otp_channel_email_verify_signup',
                                'label'       => __('Verify at Sign Up', 'wp-sms'),
                                'type'        => 'checkbox',
                                'description' => __('Email must be verified during registration', 'wp-sms'),
                                'default'     => true,
                            ]),
                            new Field([
                                'key'         => 'otp_channel_email_allow_username_on_login',
                                'label'       => __('Allow Username Feild On Login', 'wp-sms'),
                                'type'        => 'checkbox',
                                'description' => __('', 'wp-sms'),
                                'default'     => true,
                            ]),
                            new Field([
                                'key'         => 'otp_channel_email_allow_signin',
                                'label'       => __('Allow to Sign In', 'wp-sms'),
                                'type'        => 'checkbox',
                                'description' => __('Allow users to sign in using email', 'wp-sms'),
                                'default'     => true,
                            ])
                        ]
                    ]),
                ]
            ]),
            new Section([
                'id'       => 'branding_sms_templates',
                'title'    => __('Branding', 'wp-sms'),
                'subtitle' => __('SMS Templates', 'wp-sms'),
                'order'    => 100,
                'fields'   => [
                    new Field([
                        'key'               => 'sms_tpl_otp_body',
                        'label'             => __('OTP Code SMS — Body (Text only)', 'wp-sms'),
                        'type'              => 'textarea',
                        'rows'              => 6,
                        'group_label'       => __('OTP Code SMS', 'wp-sms'),
                        'description'       => sprintf(
                            __('Plain text only. Max 2000 chars. Placeholders: %s', 'wp-sms'),
                            esc_html('{{otp_code}} {{expires_in_minutes}} {{user_display_name}} {{site_name}}')
                        ),
                        'placeholder'       => __('{{site_name}} code: {{otp_code}}. Expires in {{expires_in_minutes}} min. If this wasn’t you, ignore.', 'wp-sms'),
                        'sanitize_callback' => ['\\WP_SMS\\Services\\OTP\\Delivery\\PhoneNumber\\Templating\\SanitizeCallbacks', 'body'],
                    ]),
                    new Field([
                        'key'               => 'sms_tpl_otp_revert',
                        'label'             => __('Revert to Default', 'wp-sms'),
                        'type'              => 'checkbox',
                        'group_label'       => __('OTP Code SMS', 'wp-sms'),
                        'description'       => __('If checked, this template uses the default body.', 'wp-sms'),
                        'default'           => false,
                        'sanitize_callback' => ['\\WP_SMS\\Services\\OTP\\Delivery\\PhoneNumber\\Templating\\SanitizeCallbacks', 'revert'],
                    ]),

                    new Field([
                        'key'               => 'sms_tpl_magic_body',
                        'label'             => __('Magic Link SMS — Body (Text only)', 'wp-sms'),
                        'type'              => 'textarea',
                        'rows'              => 6,
                        'group_label'       => __('Magic Link SMS', 'wp-sms'),
                        'description'       => sprintf(
                            __('Plain text only. Max 2000 chars. Placeholders: %s', 'wp-sms'),
                            esc_html('{{magic_link}} {{user_display_name}} {{expires_in_minutes}} {{site_name}}')
                        ),
                        'placeholder'       => __('{{site_name}} login: {{magic_link}} (valid {{expires_in_minutes}} min).', 'wp-sms'),
                        'sanitize_callback' => ['\\WP_SMS\\Services\\OTP\\Delivery\\PhoneNumber\\Templating\\SanitizeCallbacks', 'body'],
                    ]),
                    new Field([
                        'key'               => 'sms_tpl_magic_revert',
                        'label'             => __('Revert to Default', 'wp-sms'),
                        'type'              => 'checkbox',
                        'group_label'       => __('Magic Link SMS', 'wp-sms'),
                        'default'           => false,
                        'sanitize_callback' => ['\\WP_SMS\\Services\\OTP\\Delivery\\PhoneNumber\\Templating\\SanitizeCallbacks', 'revert'],
                    ]),

                    new Field([
                        'key'               => 'sms_tpl_reset_body',
                        'label'             => __('Password Reset SMS — Body (Text only)', 'wp-sms'),
                        'type'              => 'textarea',
                        'rows'              => 6,
                        'group_label'       => __('Password Reset SMS', 'wp-sms'),
                        'description'       => sprintf(
                            __('Plain text only. Max 2000 chars. Placeholders: %s', 'wp-sms'),
                            esc_html('{{reset_link}} {{user_display_name}} {{expires_in_minutes}} {{site_name}}')
                        ),
                        'placeholder'       => __('Reset {{site_name}} password: {{reset_link}} (valid {{expires_in_minutes}} min).', 'wp-sms'),
                        'sanitize_callback' => ['\\WP_SMS\\Services\\OTP\\Delivery\\PhoneNumber\\Templating\\SanitizeCallbacks', 'body'],
                    ]),
                    new Field([
                        'key'               => 'sms_tpl_reset_revert',
                        'label'             => __('Revert to Default', 'wp-sms'),
                        'type'              => 'checkbox',
                        'group_label'       => __('Password Reset SMS', 'wp-sms'),
                        'default'           => false,
                        'sanitize_callback' => ['\\WP_SMS\\Services\\OTP\\Delivery\\PhoneNumber\\Templating\\SanitizeCallbacks', 'revert'],
                    ]),
                ],
            ])
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
}
