<?php

namespace WP_SMS\Settings\Groups;

use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\Field;
use WP_SMS\Settings\Section;
use WP_SMS\Settings\LucideIcons;

class OTPChannelSettings extends AbstractSettingGroup
{
    public function getName(): string
    {
        return 'otp';
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
        return true;
    }

    public function getSections(): array
    {
        return [
            new Section([
                'id' => 'otp_channels',
                'title' => __('OTP Channels', 'wp-sms'),
                'subtitle' => __('Configure OTP verification channels and their settings', 'wp-sms'),
                'order' => 2,
                'fields' => [
                    // Username Channel
                    new Field([
                        'key' => 'username_channel',
                        'label' => __('Username', 'wp-sms'),
                        'type' => 'checkbox',
                        'default' => false,
                        'sub_fields' => [
                            new Field([
                                'key' => 'username_min_length',
                                'label' => __('Minimum Length', 'wp-sms'),
                                'type' => 'number',
                                'description' => __('Minimum username length requirement', 'wp-sms'),
                                'default' => 3,
                                'min' => 2,
                                'max' => 10,
                            ]),
                            new Field([
                                'key' => 'username_max_length',
                                'label' => __('Maximum Length', 'wp-sms'),
                                'type' => 'number',
                                'description' => __('Maximum username length requirement', 'wp-sms'),
                                'default' => 20,
                                'min' => 5,
                                'max' => 50,
                            ]),
                            new Field([
                                'key' => 'username_unique_requirement',
                                'label' => __('Require Unique Username', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Ensure usernames are unique across the system', 'wp-sms'),
                                'default' => true,
                            ]),
                            new Field([
                                'key' => 'username_case_sensitive',
                                'label' => __('Case Sensitive', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Treat usernames as case sensitive', 'wp-sms'),
                                'default' => false,
                            ]),
                            new Field([
                                'key' => 'username_allowed_characters',
                                'label' => __('Allowed Characters', 'wp-sms'),
                                'type' => 'multiselect',
                                'description' => __('Select which characters are allowed in usernames', 'wp-sms'),
                                'options' => [
                                    'alphanumeric' => __('Alphanumeric (a-z, A-Z, 0-9)', 'wp-sms'),
                                    'underscores' => __('Underscores (_)', 'wp-sms'),
                                    'hyphens' => __('Hyphens (-)', 'wp-sms'),
                                    'dots' => __('Dots (.)', 'wp-sms'),
                                ],
                                'default' => ['alphanumeric', 'underscores', 'hyphens', 'dots'],
                            ]),
                            new Field([
                                'key' => 'username_reserved_words',
                                'label' => __('Reserved Words (Blacklist)', 'wp-sms'),
                                'type' => 'textarea',
                                'description' => __('Comma-separated list of reserved usernames that cannot be used', 'wp-sms'),
                                'placeholder' => 'admin, root, system, test, demo',
                                'default' => 'admin, root, system, test, demo',
                                'rows' => 3,
                            ]),
                            new Field([
                                'key' => 'username_real_time_check',
                                'label' => __('Real-time Availability Check', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Check username availability in real-time as user types', 'wp-sms'),
                                'default' => true,
                            ]),
                            new Field([
                                'key' => 'username_field_label',
                                'label' => __('Username Field Label', 'wp-sms'),
                                'type' => 'text',
                                'description' => __('Custom label for the username field in forms', 'wp-sms'),
                                'default' => __('Username', 'wp-sms'),
                                'placeholder' => __('Username', 'wp-sms'),
                            ]),
                        ]
                    ]),
                    // Password Channel
                    new Field([
                        'key' => 'password_channel',
                        'label' => __('Password', 'wp-sms'),
                        'type' => 'checkbox',
                        'default' => false,
                        'sub_fields' => [
                            new Field([
                                'key' => 'password_verification_method',
                                'label' => __('Verification Method', 'wp-sms'),
                                'type' => 'multiselect',
                                'description' => __('Select verification methods for password channel', 'wp-sms'),
                                'options' => [
                                    'otp' => __('OTP Code', 'wp-sms'),
                                    'link' => __('Verification Link', 'wp-sms'),
                                    'both' => __('Both Methods', 'wp-sms'),
                                ],
                                'default' => ['otp'],
                            ]),
                            new Field([
                                'key' => 'password_min_length',
                                'label' => __('Minimum Length', 'wp-sms'),
                                'type' => 'number',
                                'description' => __('Minimum password length requirement', 'wp-sms'),
                                'default' => 8,
                                'min' => 6,
                                'max' => 20,
                            ]),
                            new Field([
                                'key' => 'password_max_length',
                                'label' => __('Maximum Length', 'wp-sms'),
                                'type' => 'number',
                                'description' => __('Maximum password length requirement', 'wp-sms'),
                                'default' => 128,
                                'min' => 10,
                                'max' => 255,
                            ]),
                            new Field([
                                'key' => 'password_require_uppercase',
                                'label' => __('Require Uppercase', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Password must contain at least one uppercase letter', 'wp-sms'),
                                'default' => true,
                            ]),
                            new Field([
                                'key' => 'password_require_lowercase',
                                'label' => __('Require Lowercase', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Password must contain at least one lowercase letter', 'wp-sms'),
                                'default' => true,
                            ]),
                            new Field([
                                'key' => 'password_require_numbers',
                                'label' => __('Require Numbers', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Password must contain at least one number', 'wp-sms'),
                                'default' => true,
                            ]),
                            new Field([
                                'key' => 'password_require_special',
                                'label' => __('Require Special Characters', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Password must contain at least one special character', 'wp-sms'),
                                'default' => false,
                            ]),
                            new Field([
                                'key' => 'password_strength_meter',
                                'label' => __('Show Strength Meter', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Display password strength indicator to users', 'wp-sms'),
                                'default' => true,
                            ]),
                            new Field([
                                'key' => 'password_expiry_days',
                                'label' => __('Password Expiry (days)', 'wp-sms'),
                                'type' => 'number',
                                'description' => __('Number of days before password expires (0 = never)', 'wp-sms'),
                                'default' => 90,
                                'min' => 0,
                                'max' => 365,
                            ]),
                        ]
                    ]),
                    // Phone Channel
                    new Field([
                        'key' => 'phone_channel',
                        'label' => __('Phone Channel', 'wp-sms'),
                        'type' => 'checkbox',
                        'default' => false,
                        'sub_fields' => [
                            new Field([
                                'key' => 'phone_verification_method',
                                'label' => __('Verification Method', 'wp-sms'),
                                'type' => 'multiselect',
                                'description' => __('Select verification methods for phone channel', 'wp-sms'),
                                'options' => [
                                    'otp' => __('OTP Code', 'wp-sms'),
                                    'link' => __('Verification Link', 'wp-sms'),
                                    'both' => __('Both Methods', 'wp-sms'),
                                ],
                                'default' => ['otp'],
                            ]),
                            new Field([
                                'key' => 'phone_otp_digits',
                                'label' => __('OTP Digits', 'wp-sms'),
                                'type' => 'number',
                                'description' => __('Number of digits in OTP codes for phone verification', 'wp-sms'),
                                'default' => 6,
                                'min' => 4,
                                'max' => 8,
                            ]),
                            new Field([
                                'key' => 'phone_expiry_seconds',
                                'label' => __('OTP Expiry (seconds)', 'wp-sms'),
                                'type' => 'number',
                                'description' => __('Time in seconds before phone OTP codes expire', 'wp-sms'),
                                'default' => 300,
                                'min' => 60,
                                'max' => 1800,
                                'step' => 30,
                            ]),
                            new Field([
                                'key' => 'phone_sms',
                                'label' => __('Enable SMS Delivery', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Allow OTP delivery via SMS', 'wp-sms'),
                                'default' => true,
                            ]),
                            new Field([
                                'key' => 'phone_whatsapp',
                                'label' => __('Enable WhatsApp Delivery', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Allow OTP delivery via WhatsApp (Coming Soon)', 'wp-sms'),
                                'default' => false,
                                'readonly' => true,
                            ]),
                            new Field([
                                'key' => 'phone_viber',
                                'label' => __('Enable Viber Delivery', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Allow OTP delivery via Viber (Coming Soon)', 'wp-sms'),
                                'default' => false,
                                'readonly' => true,
                            ]),
                            new Field([
                                'key' => 'phone_call',
                                'label' => __('Enable Phone Call Delivery', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Allow OTP delivery via phone call (Coming Soon)', 'wp-sms'),
                                'default' => false,
                                'readonly' => true,
                            ]),
                            new Field([
                                'key' => 'phone_sms_provider',
                                'label' => __('SMS Provider', 'wp-sms'),
                                'type' => 'select',
                                'description' => __('Select the SMS provider for OTP delivery', 'wp-sms'),
                                'options' => [
                                    '' => __('Default Provider', 'wp-sms'),
                                    'twilio' => __('Twilio', 'wp-sms'),
                                    'nexmo' => __('Vonage (Nexmo)', 'wp-sms'),
                                    'aws' => __('AWS SNS', 'wp-sms'),
                                ],
                                'show_if' => ['phone_sms' => true],
                            ]),
                        ]
                    ]),
                    // Email Channel
                    new Field([
                        'key' => 'email_channel',
                        'label' => __('Email', 'wp-sms'),
                        'type' => 'checkbox',
                        'default' => false,
                        'sub_fields' => [
                            new Field([
                                'key' => 'email_verification_method',
                                'label' => __('Verification Method', 'wp-sms'),
                                'type' => 'multiselect',
                                'description' => __('Select verification methods for email channel', 'wp-sms'),
                                'options' => [
                                    'otp' => __('OTP Code', 'wp-sms'),
                                    'link' => __('Verification Link', 'wp-sms'),
                                    'both' => __('Both Methods', 'wp-sms'),
                                ],
                                'default' => ['otp'],
                            ]),
                            new Field([
                                'key' => 'email_otp_digits',
                                'label' => __('OTP Digits', 'wp-sms'),
                                'type' => 'number',
                                'description' => __('Number of digits in OTP codes for email verification', 'wp-sms'),
                                'default' => 6,
                                'min' => 4,
                                'max' => 8,
                            ]),
                            new Field([
                                'key' => 'email_expiry_seconds',
                                'label' => __('OTP Expiry (seconds)', 'wp-sms'),
                                'type' => 'number',
                                'description' => __('Time in seconds before email OTP codes expire', 'wp-sms'),
                                'default' => 300,
                                'min' => 60,
                                'max' => 1800,
                                'step' => 30,
                            ]),
                            new Field([
                                'key' => 'email_from_address',
                                'label' => __('From Email Address', 'wp-sms'),
                                'type' => 'email',
                                'description' => __('Email address used as sender for OTP emails', 'wp-sms'),
                                'default' => 'noreply@example.com',
                                'placeholder' => 'noreply@yourdomain.com',
                            ]),
                            new Field([
                                'key' => 'email_template',
                                'label' => __('Email Template', 'wp-sms'),
                                'type' => 'select',
                                'description' => __('Select the email template for OTP delivery', 'wp-sms'),
                                'options' => [
                                    'default' => __('Default Template', 'wp-sms'),
                                    'minimal' => __('Minimal Template', 'wp-sms'),
                                    'branded' => __('Branded Template', 'wp-sms'),
                                ],
                                'default' => 'default',
                            ]),
                        ]
                    ]),
                ]
            ]),
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
