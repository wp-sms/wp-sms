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
