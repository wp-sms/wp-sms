<?php

namespace WP_SMS\Settings\Groups;

use WP_SMS\Settings\Field;
use WP_SMS\Settings\Abstracts\AbstractSettingGroup;

class FeatureSettings extends AbstractSettingGroup
{
    public function getName(): string
    {
        return 'features';
    }

    public function getLabel(): string
    {
        return 'Feature';
    }

    public function getFields(): array
    {
        $isPro    = $this->proIsInstalled();
        $isWooPro = $this->wooProIsInstalled();

        return [
            new Field([
                'key'         => 'admin_reports',
                'type'        => 'header',
                'label'       => 'Administrative Reporting',
                'group_label' => 'Features',
            ]),
            new Field([
                'key'         => 'report_wpsms_statistics',
                'type'        => 'checkbox',
                'label'       => 'SMS Performance Reports',
                'description' => 'Sends weekly SMS performance statistics to the admin email.',
                'group_label' => 'Features',
            ]),
            new Field([
                'key'         => 'notify_errors_to_admin_email',
                'type'        => 'checkbox',
                'label'       => 'SMS Transmission Error Alerts',
                'description' => 'Notifies the admin email upon SMS transmission failures.',
                'group_label' => 'Features',
            ]),
            new Field([
                'key'         => 'short_url',
                'type'        => 'header',
                'label'       => $isPro ? 'URL Shortening via Bitly' : 'URL Shortening via Bitly (Pro)',
                'description' => '(Pro) Enable URL shortening using Bitly.',
                'group_label' => 'Features',
            ]),
            new Field([
                'key'         => 'short_url_status',
                'type'        => 'checkbox',
                'label'       => 'Shorten URLs',
                'description' => 'Converts all URLs to shortened versions using Bitly.',
                'group_label' => 'Features',
                'readonly'    => !$isPro
            ]),
            new Field([
                'key'         => 'short_url_api_token',
                'type'        => 'text',
                'label'       => 'Bitly API Key',
                'description' => 'Enter your Bitly API key here.',
                'group_label' => 'Features',
                'readonly'    => !$isPro
            ]),
            new Field([
                'key'         => 'webhooks',
                'type'        => 'header',
                'label'       => 'Webhooks Configuration',
                'description' => 'Set up your system’s Webhook URLs to integrate with external services.',
                'group_label' => 'Features',
            ]),
            new Field([
                'key'         => 'new_sms_webhook',
                'type'        => 'textarea',
                'label'       => 'Outgoing SMS Webhook',
                'description' => 'Configure the Webhook URL to be called after an SMS is sent.',
                'group_label' => 'Features',
            ]),
            new Field([
                'key'         => 'new_subscriber_webhook',
                'type'        => 'textarea',
                'label'       => 'Subscriber Registration Webhook',
                'description' => 'Webhook triggered when a new subscriber registers.',
                'group_label' => 'Features',
            ]),
            new Field([
                'key'         => 'new_incoming_sms_webhook',
                'type'        => 'textarea',
                'label'       => 'Incoming SMS Handling Webhook',
                'description' => __('Define the Webhook URL for the "<a href="https://wp-sms-pro.com/product/wp-sms-two-way/?utm_source=wp-sms&utm_medium=link&utm_campaign=settings" target="_blank">Two-Way SMS</a>" add-on that handles incoming SMS messages. Only secure HTTPS URLs are accepted.', 'wp-sms') . '<br><br /><i>' . esc_html__('Please provide each Webhook URL on a separate line if you\'re setting up more than one.', 'wp-sms') . '</i>',
                'group_label' => 'Features',
            ]),
            new Field([
                'key'         => 'g_recaptcha',
                'type'        => 'header',
                'label'       => ($isPro || $isWooPro) ? 'Google reCAPTCHA Integration' : 'Google reCAPTCHA Integration (Pro / WooCommerce Pro)',
                'description' => 'Secure SMS requests using Google reCAPTCHA.',
                'group_label' => 'Features',
            ]),
            new Field([
                'key'         => 'g_recaptcha_status',
                'type'        => 'checkbox',
                'label'       => 'Activate',
                'description' => 'Use Google reCAPTCHA for your SMS requests.',
                'group_label' => 'Features',
                'readonly'    => !$isPro && !$isWooPro
            ]),
            new Field([
                'key'         => 'g_recaptcha_site_key',
                'type'        => 'text',
                'label'       => 'Site Key',
                'description' => esc_html__('Enter your unique site key provided by Google reCAPTCHA. This public key is used in the HTML code of your site to display the reCAPTCHA widget. ', 'wp-sms') . '<a href="https://www.google.com/recaptcha/admin" target="_blank">Get your site key</a>.',
                'group_label' => 'Features',
                'readonly'    => !$isPro && !$isWooPro
            ]),
            new Field([
                'key'         => 'g_recaptcha_secret_key',
                'type'        => 'text',
                'label'       => 'Secret Key',
                'description' => esc_html__('Insert your secret key here. This private key is used for communication between your server and the reCAPTCHA server. ', 'wp-sms') . '<a href="https://www.google.com/recaptcha/admin" target="_blank">Access your secret key</a>.' . '<br />' . esc_html__('Remember, both keys are necessary and should be kept confidential. The site key can be included in your web pages, but the secret key should never be exposed publicly.', 'wp-sms'),
                'group_label' => 'Features',
                'readonly'    => !$isPro && !$isWooPro
            ]),
        ];
    }
}
