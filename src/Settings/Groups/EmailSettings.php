<?php

namespace WP_SMS\Settings\Groups;

use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\Field;
use WP_SMS\Settings\Section;
use WP_SMS\Settings\LucideIcons;

if (!defined('ABSPATH')) {
    exit;
}

class EmailSettings extends AbstractSettingGroup
{
    /**
     * @return string
     */
    public function getName(): string
    {
        return 'email';
    }

    /**
     * @return string
     */
    public function getLabel(): string
    {
        return __('Email', 'wp-sms');
    }

    /**
     * @return string
     */
    public function getIcon(): string
    {
        return LucideIcons::MAIL;
    }


    /**
     * @return bool
     */
    public function isApiVisible(): bool
    {
        return true;
    }

    /**
     * @return Section[]
     */
    public function getSections(): array
    {
        $default_from_name  = function_exists('get_bloginfo') ? get_bloginfo('name') : '';
        $default_from_email = function_exists('get_option') ? get_option('admin_email') : '';

        return [
            new Section([
                'id'       => 'email_general',
                'title'    => __('Email Delivery', 'wp-sms'),
                'subtitle' => __('Configure email delivery via WordPress wp_mail()', 'wp-sms'),
                'order'    => 1,
                'fields'   => [
                    new Field([
                        'key'         => 'from_email',
                        'label'       => __('Enable email delivery', 'wp-sms'),
                        'type'        => 'checkbox',
                        'description' => __('Use WordPress wp_mail() to send emails (used by OTP and other modules).', 'wp-sms'),
                        'default'     => false,
                    ]),
                    new Field([
                        'key'         => 'from_name',
                        'label'       => __('From name', 'wp-sms'),
                        'type'        => 'text',
                        'description' => sprintf(
                            __('Optional. Defaults to site name (%s) if left empty.', 'wp-sms'),
                            esc_html($default_from_name)
                        ),
                        'placeholder' => $default_from_name,
                        'default'     => '',
                    ]),
                    new Field([
                        'key'         => 'from_email',
                        'label'       => __('From email', 'wp-sms'),
                        'type'        => 'text',
                        'description' => sprintf(
                            __('Optional. Defaults to admin email (%s) if left empty.', 'wp-sms'),
                            esc_html($default_from_email)
                        ),
                        'placeholder' => $default_from_email,
                        'default'     => '',
                    ]),
                    new Field([
                        'key'         => 'reply_to',
                        'label'       => __('Reply-To (optional)', 'wp-sms'),
                        'type'        => 'text',
                        'description' => __('Email address to receive replies. Leave blank to omit.', 'wp-sms'),
                        'placeholder' => '',
                        'default'     => '',
                    ]),
                    new Field([
                        'key'         => 'debug_logging',
                        'label'       => __('Enable debug logging', 'wp-sms'),
                        'type'        => 'checkbox',
                        'description' => __('Adds headers and a short body preview (200 chars) to email logs. Turn off in production if not needed.', 'wp-sms'),
                        'default'     => false,
                    ]),
                ],
            ]),
        ];
    }

    /**
     * @return array
     */
    public function getFields(): array
    {
        $allFields = [];
        foreach ($this->getSections() as $section) {
            $allFields = array_merge($allFields, $section->getFields());
        }
        return $allFields;
    }
}
