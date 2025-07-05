<?php

namespace WP_SMS\Settings\Groups\Addons;

use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\Field;
use WP_SMS\Settings\Section;
use WP_SMS\Settings\Tags;
use WP_SMS\Settings\LucideIcons;
use WPSmsTwoWay\Services\Gateway\GatewayManager;
use WPSmsTwoWay\Services\Webhook\Webhook;
use WP_SMS\Notification\NotificationFactory;

class TwoWaySettings extends AbstractSettingGroup
{
    /**
     * Gateway manager instance
     *
     * @var WPSmsTwoWay\Services\Gateway\GatewayManager|null
     */
    private $gateway;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->setGateway();
    }

    public function getName(): string
    {
        return 'two_way';
    }

    public function getLabel(): string
    {
        return __('Two-Way SMS', 'wp-sms-two-way');
    }

    public function getIcon(): string
    {
        return LucideIcons::MESSAGE_CIRCLE;
    }

    public function getSections(): array
    {
        $isPluginActive = $this->isPluginActive();
        $inactiveNotice = $isPluginActive ? '' : ' <em>(' . __('Plugin not active', 'wp-sms-two-way') . ')</em>';
        
        return [
            new Section([
                'id' => 'gateway_status',
                'title' => __('Gateway Status', 'wp-sms-two-way'),
                'subtitle' => __('View your gateway status and webhook configuration', 'wp-sms-two-way') . $inactiveNotice,
                'fields' => $this->getGatewayStatusFields(),
                'readonly' => !$isPluginActive,
                'tag' => 'twoway',
                'order' => 1,
            ]),
            new Section([
                'id' => 'message_forwarding',
                'title' => __('Message Forwarding', 'wp-sms-two-way'),
                'subtitle' => __('Configure forwarding of incoming SMS messages', 'wp-sms-two-way') . $inactiveNotice,
                'fields' => $this->getMessageForwardingFields(),
                'readonly' => !$isPluginActive,
                'tag' => 'twoway',
                'order' => 2,
            ]),
            new Section([
                'id' => 'email_notifications',
                'title' => __('Email Notifications', 'wp-sms-two-way'),
                'subtitle' => __('Configure email notifications for incoming SMS messages', 'wp-sms-two-way') . $inactiveNotice,
                'fields' => $this->getEmailNotificationFields(),
                'readonly' => !$isPluginActive,
                'tag' => 'twoway',
                'order' => 3,
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

    /**
     * Get gateway status fields
     *
     * @return array
     */
    private function getGatewayStatusFields(): array
    {
        $isPluginActive = $this->isPluginActive();
        
        $fields = [
            new Field([
                'key' => 'current_gateway',
                'label' => __('Current Gateway', 'wp-sms-two-way'),
                'type' => 'html',
                'description' => $this->renderCurrentGatewayNameField(),
                'readonly' => !$isPluginActive,
                'tag' => 'twoway',
            ]),
            new Field([
                'key' => 'support_status',
                'label' => __('Support Status', 'wp-sms-two-way'),
                'type' => 'html',
                'description' => $this->renderGatewayStatusField(),
                'readonly' => !$isPluginActive,
                'tag' => 'twoway',
            ]),
        ];

        // Add conditional fields only if gateway is supported
        if ($this->gateway && $this->gateway->isSupported) {
            $fields[] = new Field([
                'key' => 'register_type',
                'label' => __('Webhook Register Type', 'wp-sms-two-way'),
                'type' => 'html',
                'description' => $this->renderRegisterTypeField(),
                'readonly' => !$isPluginActive,
                'tag' => 'twoway',
            ]);

            switch ($this->gateway->getRegisterType()) {
                case 'api':
                    $fields[] = new Field([
                        'key' => 'registration_status',
                        'label' => __('Register Webhook in API', 'wp-sms-two-way'),
                        'type' => 'html',
                        'description' => $this->renderRegistrationApiButton(),
                        'readonly' => !$isPluginActive,
                        'tag' => 'twoway',
                    ]);
                    break;
                case 'panel':
                    $fields[] = new Field([
                        'key' => 'webhook_url',
                        'label' => __('Webhook URL', 'wp-sms-two-way'),
                        'type' => 'html',
                        'description' => $this->renderWebhookUrlField(),
                        'readonly' => !$isPluginActive,
                        'tag' => 'twoway',
                    ]);
                    if ($this->gateway->getPanelUrl()) {
                        $fields[] = new Field([
                            'key' => 'registration_panel_url',
                            'label' => __('Registration panel URL', 'wp-sms-two-way'),
                            'type' => 'html',
                            'description' => $this->renderRegistrationPanelField(),
                            'readonly' => !$isPluginActive,
                            'tag' => 'twoway',
                        ]);
                    }
                    break;
            }
        }

        return $fields;
    }

    /**
     * Get message forwarding fields
     *
     * @return array
     */
    private function getMessageForwardingFields(): array
    {
        $isPluginActive = $this->isPluginActive();
        $variables = [
            '%sender_number%'   => '',
            '%sms_content%'     => '',
            '%site_name%'       => '',
            '%user_name%'       => '',
            '%subscriber_name%' => '',
        ];

        return [
            new Field([
                'key' => 'notif_new_inbox_message',
                'label' => __('Status', 'wp-sms-two-way'),
                'type' => 'checkbox',
                'description' => __('Forward incoming messages to the admin mobile number', 'wp-sms-two-way'),
                'readonly' => !$isPluginActive,
                'tag' => 'twoway',
            ]),
            new Field([
                'key' => 'notif_new_inbox_message_template',
                'label' => __('Message body', 'wp-sms-two-way'),
                'type' => 'textarea',
                'description' => __('Enter the contents of the sms message.', 'wp-sms') . '<br>' . NotificationFactory::getCustom()->registerVariables($variables)->printVariables(),
                'rows' => 5,
                'readonly' => !$isPluginActive,
                'tag' => 'twoway',
            ]),
        ];
    }

    /**
     * Get email notification fields
     *
     * @return array
     */
    private function getEmailNotificationFields(): array
    {
        $isPluginActive = $this->isPluginActive();
        
        return [
            new Field([
                'key' => 'email_new_inbox_message',
                'label' => __('Status', 'wp-sms-two-way'),
                'type' => 'checkbox',
                'description' => __('Send incoming messages to the admin email address', 'wp-sms-two-way'),
                'readonly' => !$isPluginActive,
                'tag' => 'twoway',
            ]),
        ];
    }

    /**
     * Set active gateway
     *
     * @return void
     */
    private function setGateway(): void
    {
        if (!$this->isPluginActive()) {
            $this->gateway = null;
            return;
        }
        
        try {
            $this->gateway = WPSmsTwoWay()->getPlugin()->get(GatewayManager::class)->getCurrentGateway();
        } catch (\Exception $e) {
            $this->gateway = null;
        }
    }

    /**
     * Check if the Two-Way plugin is active
     *
     * @return bool
     */
    private function isPluginActive(): bool
    {
        return function_exists('WPSmsTwoWay');
    }

    /**
     * Render name of the active gateway
     *
     * @return string
     */
    private function renderCurrentGatewayNameField(): string
    {
        if (!$this->gateway) {
            return "<p class='two-way-gateway-name'><strong>" . __('Not Available', 'wp-sms-two-way') . "</strong></p>";
        }
        
        $gatewayName = ucfirst($this->gateway->name) ?? __('Not Set', 'wp-sms-two-way');
        return "<p class='two-way-gateway-name'><strong>{$gatewayName}</strong></p>";
    }

    /**
     * Render two-way support status of the active gateway
     *
     * @return string
     */
    private function renderGatewayStatusField(): string
    {
        if (!$this->gateway) {
            return '<span class="wpsms-indicator__status inactive">
                <svg viewBox="0 0 6 6" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="3" cy="2" r="1" stroke-width="2"></circle>
                </svg>
                <span>' . __('Plugin Not Active', 'wp-sms-two-way') . '</span>
            </span>';
        }
        
        if ($this->gateway->isSupported) {
            return '<span class="wpsms-indicator__status active">
                <svg viewBox="0 0 6 6" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="3" cy="2" r="1" stroke-width="2"></circle>
                </svg>
                <span>' . __('Supported', 'wp-sms') . '</span>
            </span>';
        }

        return '<span class="wpsms-indicator__status inactive">
            <svg viewBox="0 0 6 6" xmlns="http://www.w3.org/2000/svg">
                <circle cx="3" cy="2" r="1" stroke-width="2"></circle>
            </svg>
            <span>' . __('Not Supported', 'wp-sms') . '</span>
        </span>';
    }

    /**
     * Render registration type field of the active gateway
     *
     * @return string
     */
    private function renderRegisterTypeField(): string
    {
        if (!$this->gateway) {
            return "<p>" . __('Not Available', 'wp-sms-two-way') . "</p>";
        }
        
        return sprintf("<p>%s</p>", ucfirst($this->gateway->getRegisterType()));
    }

    /**
     * Render registration status field
     *
     * @return string
     */
    private function renderRegistrationApiButton(): string
    {
        if (!$this->isPluginActive()) {
            return '<p>' . __('Plugin not available', 'wp-sms-two-way') . '</p>';
        }
        
        try {
            $webhookUrl = esc_url(WPSmsTwoWay()->getPlugin()->get(Webhook::class)->getUrl());
        } catch (\Exception $e) {
            return '<p>' . __('Unable to get webhook URL', 'wp-sms-two-way') . '</p>';
        }

        $webhookField   = "<div id='two-way-webhook-url-field'><div><span>{$webhookUrl}</span></div></div>";
        $copyButton     = '<button class="" id="two-way-copy-webhook-btn" type="button">' . __('Copy', 'wp-sms-two-way') . '</button>';
        $registerButton = '<button id="two-way-register-webhook-btn" class="button button-primary" type="button">' . __('Register Webhook', 'wp-sms-two-way') . '</button>';

        $help           = $this->gateway->registerWebhookHelp;
        $help           = $help ? '<p class="description">' . $help . '</p>' : null;

        return $registerButton
            . $webhookField
            . $copyButton
            . $help;
    }

    /**
     * Render gateway's panel URL
     *
     * @return string
     */
    private function renderRegistrationPanelField(): string
    {
        if (!$this->gateway) {
            return '<p>' . __('Not Available', 'wp-sms-two-way') . '</p>';
        }
        
        $url = esc_url($this->gateway->getPanelUrl());
        return "<a href='{$url}'>{$url}</a>";
    }

    /**
     * Render webhook URL field
     *
     * @return string
     */
    private function renderWebhookUrlField(): string
    {
        if (!$this->isPluginActive()) {
            return '<p>' . __('Plugin not available', 'wp-sms-two-way') . '</p>';
        }
        
        try {
            $webhookUrl = esc_url(WPSmsTwoWay()->getPlugin()->get(Webhook::class)->getUrl());
        } catch (\Exception $e) {
            return '<p>' . __('Unable to get webhook URL', 'wp-sms-two-way') . '</p>';
        }

        $webhookField = "<div id='two-way-webhook-url-field'><div><span>{$webhookUrl}</span></div></div>";
        $copyButton   = '<button class="" id="two-way-copy-webhook-btn" type="button">' . __('Copy', 'wp-sms-two-way') . '</button>';
        $resetButton  = '<button class="" id="two-way-reset-token-btn" type="button">' . __('Reset Token', 'wp-sms-two-way') . '</button>';
        $description  = sprintf('<p class="description">Copy this URL and paste it in your gateway panel. checkout <a href="%s" target="_blank">documention</a></p>', esc_url(WP_SMS_SITE . '/resources/wp-sms-two-way/'));

        return
            $webhookField
            . $copyButton
            . $resetButton
            . $description;
    }
} 