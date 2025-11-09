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
                'title'    => __('Login & Registration Channels', 'wp-sms'),
                'subtitle' => __('Configure primary authentication channels for login and registration', 'wp-sms'),
                'order'    => 1,
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
                                    'password' => __('Password', 'wp-sms'),
                                ],
                                'default'     => ['otp'],
                            ]),
                            new Field([
                                'key'         => 'otp_channel_phone_password_is_required',
                                'label'       => __('Password is Required', 'wp-sms'),
                                'type'        => 'checkbox',
                                'description' => __('Password is required during user registration', 'wp-sms'),
                                'default'     => false,
                                'show_if'     => [
                                    'otp_channel_phone_verification_method' => 'password',
                                ],
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
                                'default'     => false,
                            ]),
                            new Field([
                                'key'         => 'otp_channel_phone_allow_signin',
                                'label'       => __('Allow to Sign In', 'wp-sms'),
                                'type'        => 'checkbox',
                                'description' => __('Allow users to sign in using phone number', 'wp-sms'),
                                'default'     => false,
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
                                    'password' => __('Password', 'wp-sms'),
                                ],
                                'default'     => ['otp'],
                            ]),
                            new Field([
                                'key'         => 'otp_channel_email_password_is_required',
                                'label'       => __('Password is Required', 'wp-sms'),
                                'type'        => 'checkbox',
                                'description' => __('Password is required during user registration', 'wp-sms'),
                                'default'     => false,
                                'show_if'     => [
                                    'otp_channel_email_verification_method' => 'password',
                                ],
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
                                'default'     => false,
                            ]),
                            new Field([
                                'key'         => 'otp_channel_email_allow_username_on_login',
                                'label'       => __('Allow Username Feild On Login', 'wp-sms'),
                                'type'        => 'checkbox',
                                'description' => __('Allow username field on login', 'wp-sms'),
                                'default'     => false,
                            ]),
                            new Field([
                                'key'         => 'otp_channel_email_allow_signin',
                                'label'       => __('Allow to Sign In', 'wp-sms'),
                                'type'        => 'checkbox',
                                'description' => __('Allow users to sign in using email', 'wp-sms'),
                                'default'     => false,
                            ])
                        ]
                    ]),
                ]
            ]),
            
            // MFA Section
            new Section([
                'id'       => 'mfa_channels',
                'title'    => __('Multi-Factor Authentication (MFA)', 'wp-sms'),
                'subtitle' => __('Configure second-factor authentication methods for enhanced security', 'wp-sms'),
                'order'    => 2,
                'fields'   => [
                    // Email MFA
                    new Field([
                        'key'        => 'otp_mfa_channel_email',
                        'label'      => __('Email (2FA)', 'wp-sms'),
                        'type'       => 'checkbox',
                        'default'    => false,
                        'description' => __('Enable email as a second factor authentication method', 'wp-sms'),
                        'disabled_if' => [
                            'otp_channel_email' => true,
                        ],
                        'sub_fields' => [
                            new Field([
                                'key'         => 'otp_mfa_channel_email_verification_method',
                                'label'       => __('MFA Method', 'wp-sms'),
                                'type'        => 'multiselect',
                                'description' => __('Select MFA methods for email channel', 'wp-sms'),
                                'options'     => [
                                    'otp'  => __('OTP Code', 'wp-sms'),
                                    'link' => __('Verification Link', 'wp-sms'),
                                ],
                                'default'     => ['otp'],
                            ]),
                            new Field([
                                'key'         => 'otp_mfa_channel_email_otp_digits',
                                'label'       => __('OTP Digits', 'wp-sms'),
                                'type'        => 'number',
                                'description' => __('Number of digits in MFA OTP codes for email', 'wp-sms'),
                                'default'     => 6,
                                'min'         => 4,
                                'max'         => 8,
                            ]),
                            new Field([
                                'key'         => 'otp_mfa_channel_email_expiry_seconds',
                                'label'       => __('OTP Expiry (seconds)', 'wp-sms'),
                                'type'        => 'number',
                                'description' => __('Time in seconds before email MFA codes expire', 'wp-sms'),
                                'default'     => 300,
                                'min'         => 60,
                                'max'         => 1800,
                                'step'        => 30,
                            ]),
                            new Field([
                                'key'         => 'otp_mfa_channel_email_required',
                                'label'       => __('Required for All Users', 'wp-sms'),
                                'type'        => 'checkbox',
                                'description' => __('Require all users to use email MFA', 'wp-sms'),
                                'default'     => false,
                            ]),
                        ]
                    ]),
                    
                    // Phone MFA
                    new Field([
                        'key'        => 'otp_mfa_channel_phone',
                        'label'      => __('Phone Number (2FA)', 'wp-sms'),
                        'type'       => 'checkbox',
                        'default'    => false,
                        'description' => __('Enable phone as a second factor authentication method', 'wp-sms'),
                        'disabled_if' => [
                            'otp_channel_phone' => true,
                        ],
                        'sub_fields' => [
                            new Field([
                                'key'         => 'otp_mfa_channel_phone_verification_method',
                                'label'       => __('MFA Method', 'wp-sms'),
                                'type'        => 'multiselect',
                                'description' => __('Select MFA methods for phone channel', 'wp-sms'),
                                'options'     => [
                                    'otp'  => __('OTP Code', 'wp-sms'),
                                    'link' => __('Verification Link', 'wp-sms'),
                                ],
                                'default'     => ['otp'],
                            ]),
                            new Field([
                                'key'         => 'otp_mfa_channel_phone_otp_digits',
                                'label'       => __('OTP Digits', 'wp-sms'),
                                'type'        => 'number',
                                'description' => __('Number of digits in MFA OTP codes for phone', 'wp-sms'),
                                'default'     => 6,
                                'min'         => 4,
                                'max'         => 8,
                            ]),
                            new Field([
                                'key'         => 'otp_mfa_channel_phone_expiry_seconds',
                                'label'       => __('OTP Expiry (seconds)', 'wp-sms'),
                                'type'        => 'number',
                                'description' => __('Time in seconds before phone MFA codes expire', 'wp-sms'),
                                'default'     => 300,
                                'min'         => 60,
                                'max'         => 1800,
                                'step'        => 30,
                            ]),
                            new Field([
                                'key'         => 'otp_mfa_channel_phone_sms',
                                'label'       => __('Enable SMS Delivery', 'wp-sms'),
                                'type'        => 'checkbox',
                                'description' => __('Allow MFA delivery via SMS', 'wp-sms'),
                                'default'     => true,
                            ]),
                            new Field([
                                'key'         => 'otp_mfa_channel_phone_whatsapp',
                                'label'       => __('Enable WhatsApp Delivery', 'wp-sms'),
                                'type'        => 'checkbox',
                                'description' => __('Allow MFA delivery via WhatsApp', 'wp-sms'),
                                'default'     => false,
                                'readonly'    => true,
                                'tag'         => Tags::COMING_SOON,
                            ]),
                            new Field([
                                'key'         => 'otp_mfa_channel_phone_required',
                                'label'       => __('Required for All Users', 'wp-sms'),
                                'type'        => 'checkbox',
                                'description' => __('Require all users to use phone MFA', 'wp-sms'),
                                'default'     => false,
                            ]),
                        ]
                    ]),
                    
                    // TOTP (Coming Soon)
                    new Field([
                        'key'        => 'otp_mfa_channel_totp',
                        'label'      => __('TOTP (Authenticator App)', 'wp-sms'),
                        'type'       => 'checkbox',
                        'default'    => false,
                        'readonly'   => true,
                        'tag'        => Tags::COMING_SOON,
                        'description' => __('Enable Time-based OTP via authenticator apps (Google Authenticator, Authy, etc.)', 'wp-sms'),
                        'sub_fields' => [
                            new Field([
                                'key'         => 'otp_mfa_channel_totp_issuer',
                                'label'       => __('Issuer Name', 'wp-sms'),
                                'type'        => 'text',
                                'description' => __('Name shown in authenticator app', 'wp-sms'),
                                'default'     => get_bloginfo('name'),
                                'readonly'    => true,
                                'tag'         => Tags::COMING_SOON,
                            ]),
                            new Field([
                                'key'         => 'otp_mfa_channel_totp_digits',
                                'label'       => __('TOTP Digits', 'wp-sms'),
                                'type'        => 'number',
                                'description' => __('Number of digits in TOTP codes', 'wp-sms'),
                                'default'     => 6,
                                'min'         => 6,
                                'max'         => 8,
                                'readonly'    => true,
                                'tag'         => Tags::COMING_SOON,
                            ]),
                            new Field([
                                'key'         => 'otp_mfa_channel_totp_period',
                                'label'       => __('Time Period (seconds)', 'wp-sms'),
                                'type'        => 'number',
                                'description' => __('TOTP refresh interval', 'wp-sms'),
                                'default'     => 30,
                                'readonly'    => true,
                                'tag'         => Tags::COMING_SOON,
                            ]),
                            new Field([
                                'key'         => 'otp_mfa_channel_totp_required',
                                'label'       => __('Required for All Users', 'wp-sms'),
                                'type'        => 'checkbox',
                                'description' => __('Require all users to use TOTP MFA', 'wp-sms'),
                                'default'     => false,
                                'readonly'    => true,
                                'tag'         => Tags::COMING_SOON,
                            ]),
                        ]
                    ]),
                    
                    // Biometric (Coming Soon)
                    new Field([
                        'key'        => 'otp_mfa_channel_biometric',
                        'label'      => __('Biometric (WebAuthn)', 'wp-sms'),
                        'type'       => 'checkbox',
                        'default'    => false,
                        'readonly'   => true,
                        'tag'        => Tags::COMING_SOON,
                        'description' => __('Enable biometric authentication via WebAuthn (Face ID, Touch ID, Fingerprint, Security Keys)', 'wp-sms'),
                        'sub_fields' => [
                            new Field([
                                'key'         => 'otp_mfa_channel_biometric_attestation',
                                'label'       => __('Attestation Type', 'wp-sms'),
                                'type'        => 'select',
                                'description' => __('Security level for device verification', 'wp-sms'),
                                'options'     => [
                                    'none'     => __('None', 'wp-sms'),
                                    'indirect' => __('Indirect', 'wp-sms'),
                                    'direct'   => __('Direct', 'wp-sms'),
                                ],
                                'default'     => 'none',
                                'readonly'    => true,
                                'tag'         => Tags::COMING_SOON,
                            ]),
                            new Field([
                                'key'         => 'otp_mfa_channel_biometric_user_verification',
                                'label'       => __('User Verification', 'wp-sms'),
                                'type'        => 'select',
                                'description' => __('Require user verification (PIN, biometric)', 'wp-sms'),
                                'options'     => [
                                    'required'    => __('Required', 'wp-sms'),
                                    'preferred'   => __('Preferred', 'wp-sms'),
                                    'discouraged' => __('Discouraged', 'wp-sms'),
                                ],
                                'default'     => 'required',
                                'readonly'    => true,
                                'tag'         => Tags::COMING_SOON,
                            ]),
                            new Field([
                                'key'         => 'otp_mfa_channel_biometric_required',
                                'label'       => __('Required for All Users', 'wp-sms'),
                                'type'        => 'checkbox',
                                'description' => __('Require all users to use biometric MFA', 'wp-sms'),
                                'default'     => false,
                                'readonly'    => true,
                                'tag'         => Tags::COMING_SOON,
                            ]),
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
