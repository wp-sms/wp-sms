<?php

namespace WP_SMS\Settings\Groups;

use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\Field;
use WP_SMS\Settings\Section;
use WP_SMS\Settings\LucideIcons;
use WP_SMS\Settings\Tags;
use WP_SMS\Version;

class AdvancedSettings extends AbstractSettingGroup
{
    public function getName(): string
    {
        return 'advanced';
    }

    public function getLabel(): string
    {
        return __('Advanced', 'wp-sms');
    }

    public function getIcon(): string
    {
        return LucideIcons::BADGE_CHECK;
    }

    public function getSections(): array
    {
        return [
            new Section([
                'id' => 'administrative_reporting',
                'title' => __('Administrative Reporting', 'wp-sms'),
                'subtitle' => __('Configure automated reporting and error notifications', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'report_wpsms_statistics',
                        'label' => __('SMS Performance Reports', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Sends weekly SMS performance statistics to the admin email.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'notify_errors_to_admin_email',
                        'label' => __('SMS Transmission Error Alerts', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Notifies the admin email upon SMS transmission failures.', 'wp-sms')
                    ]),
                ]
            ]),
            new Section([
                'id' => 'url_shortening',
                'title' => $this->getUrlShorteningTitle(),
                'subtitle' => __('Configure URL shortening via Bitly service', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'short_url_status',
                        'label' => __('Shorten URLs', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Converts all URLs to shortened versions using <a href="https://bitly.com/" target="_blank">Bitly.com</a>.', 'wp-sms'),
                        'readonly' => !$this->proIsInstalled(),
                        'tag' => !$this->proIsInstalled() ? Tags::PRO : null
                    ]),
                    new Field([
                        'key' => 'short_url_api_token',
                        'label' => __('Bitly API Key', 'wp-sms'),
                        'type' => 'text',
                        'description' => __('Enter your Bitly API key here. Obtain it from <a href="https://app.bitly.com/settings/api/" target="_blank">Bitly API Settings</a>.', 'wp-sms'),
                        'readonly' => !$this->proIsInstalled(),
                        'show_if' => ['short_url_status' => true]
                    ]),
                ]
            ]),
            new Section([
                'id' => 'webhooks_configuration',
                'title' => __('Webhooks Configuration', 'wp-sms'),
                'subtitle' => __('Set up your system\'s Webhook URLs to integrate with external services.', 'wp-sms'),
                'help_url' => '/resources/webhooks/',
                'fields' => [
                    new Field([
                        'key' => 'new_sms_webhook',
                        'label' => __('Outgoing SMS Webhook', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Configure the Webhook URL to which notifications are sent after an SMS dispatch from your system. Please enter a secure URL (HTTPS).', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'new_subscriber_webhook',
                        'label' => __('Subscriber Registration Webhook', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Provide the Webhook URL that will be triggered when a new subscriber registers. Ensure the URL uses the HTTPS protocol.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'new_incoming_sms_webhook',
                        'label' => __('Incoming SMS Handling Webhook', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Define the Webhook URL for the "<a href="https://wp-sms-pro.com/product/wp-sms-two-way/?utm_source=wp-sms&utm_medium=link&utm_campaign=settings" target="_blank">Two-Way SMS</a>" add-on that handles incoming SMS messages. Only secure HTTPS URLs are accepted.', 'wp-sms') . '<br><br /><i>' . __('Please provide each Webhook URL on a separate line if you\'re setting up more than one.', 'wp-sms') . '</i>'
                    ]),
                ]
            ]),
            new Section([
                'id' => 'google_recaptcha_integration',
                'title' => $this->getRecaptchaTitle(),
                'subtitle' => __('Enhance your system\'s security by activating Google reCAPTCHA. This tool prevents spam and abuse by ensuring that only genuine users can initiate request-SMS actions. Upon activation, every SMS request will be secured with reCAPTCHA verification.', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'g_recaptcha_status',
                        'label' => __('Activate', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Use Google reCAPTCHA for your SMS requests.', 'wp-sms'),
                        'readonly' => !$this->proIsInstalled() && !$this->wooProIsInstalled(),
                        'tag' => (!$this->proIsInstalled() && !$this->wooProIsInstalled()) ? Tags::PRO : null
                    ]),
                    new Field([
                        'key' => 'g_recaptcha_site_key',
                        'label' => __('Site Key', 'wp-sms'),
                        'type' => 'text',
                        'description' => __('Enter your unique site key provided by Google reCAPTCHA. This public key is used in the HTML code of your site to display the reCAPTCHA widget. ', 'wp-sms') . '<a href="https://www.google.com/recaptcha/admin" target="_blank">Get your site key</a>.',
                        'readonly' => !$this->proIsInstalled() && !$this->wooProIsInstalled(),
                        'show_if' => ['g_recaptcha_status' => true]
                    ]),
                    new Field([
                        'key' => 'g_recaptcha_secret_key',
                        'label' => __('Secret Key', 'wp-sms'),
                        'type' => 'text',
                        'description' => __('Insert your secret key here. This private key is used for communication between your server and the reCAPTCHA server. ', 'wp-sms') . '<a href="https://www.google.com/recaptcha/admin" target="_blank">Access your secret key</a>.' . '<br />' . __('Remember, both keys are necessary and should be kept confidential. The site key can be included in your web pages, but the secret key should never be exposed publicly.', 'wp-sms'),
                        'readonly' => !$this->proIsInstalled() && !$this->wooProIsInstalled(),
                        'show_if' => ['g_recaptcha_status' => true]
                    ]),
                ]
            ]),
        ];
    }

    private function getUrlShorteningTitle(): string
    {
        if (!$this->proIsInstalled()) {
            return __('URL Shortening via Bitly (Pro)', 'wp-sms');
        }
        return __('URL Shortening via Bitly', 'wp-sms');
    }

    private function getRecaptchaTitle(): string
    {
        if (!$this->proIsInstalled() && !$this->wooProIsInstalled()) {
            return __('Google reCAPTCHA Integration (Pro / WooCommerce Pro)', 'wp-sms');
        }
        return __('Google reCAPTCHA Integration', 'wp-sms');
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