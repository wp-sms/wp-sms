<?php

namespace WP_SMS\Settings\Groups;

use WP_SMS\Gateway;
use WP_SMS\Settings\Field;
use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\Section;
use WP_SMS\Settings\LucideIcons;
use WP_SMS\Settings\Tags;

class GatewaySettings extends AbstractSettingGroup {
    public function getName(): string {
        return 'gateway';
    }

    public function getLabel(): string {
        return __('SMS Gateway', 'wp-sms');
    }

    public function getIcon(): string {
        return LucideIcons::MESSAGE_SQUARE;
    }

    public function getSections(): array {
        return [
            new Section([
                'id' => 'sms_gateway_setup',
                'title' => __('Connect Your Gateway', 'wp-sms'),
                'subtitle' => __('Set your provider and add the needed credentials.', 'wp-sms'),
                'fields' => $this->getGatewaySetupFields()
            ]),
            new Section([
                'id' => 'gateway_overview',
                'title' => __('Gateway Status', 'wp-sms'),
                'subtitle' => __('Check connection and what your gateway supports.', 'wp-sms'),
                // Note: Consider adding a "Refresh status" action to re-check balance and capabilities
                'fields' => [
                    new Field([
                        'key' => 'account_credit',
                        'label' => __('Connection Status', 'wp-sms'),
                        'type' => 'html',
                        'description' => Gateway::status() ?: '<span class="wpsms-indicator__status inactive">
    <svg viewBox="0 0 6 6" xmlns="http://www.w3.org/2000/svg">
        <circle cx="3" cy="2" r="1" stroke-width="2"></circle>
    </svg>
    <span>Unavailable
</span>'
                    ]),
                    new Field([
                        'key' => 'account_response',
                        'label' => __('Account Balance', 'wp-sms'),
                        'type' => 'html',
                        'description' => Gateway::response() ?: '<span class="wpsms-indicator__status inactive">
    <svg viewBox="0 0 6 6" xmlns="http://www.w3.org/2000/svg">
        <circle cx="3" cy="2" r="1" stroke-width="2"></circle>
    </svg>
    <span>Unavailable
</span>'
                    ]),
                    new Field([
                        'key' => 'incoming_message',
                        'label' => __('Inbound SMS', 'wp-sms'),
                        'type' => 'html',
                        'description' => Gateway::incoming_message_status() ?: '<span class="wpsms-indicator__status inactive">
    <svg viewBox="0 0 6 6" xmlns="http://www.w3.org/2000/svg">
        <circle cx="3" cy="2" r="1" stroke-width="2"></circle>
    </svg>
    <span>Unavailable
</span>'
                    ]),
                    new Field([
                        'key' => 'bulk_send',
                        'label' => __('Bulk Sending', 'wp-sms'),
                        'type' => 'html',
                        'description' => Gateway::bulk_status() ?: '<span class="wpsms-indicator__status inactive">
    <svg viewBox="0 0 6 6" xmlns="http://www.w3.org/2000/svg">
        <circle cx="3" cy="2" r="1" stroke-width="2"></circle>
    </svg>
    <span>Unavailable
</span>'
                    ]),
                    new Field([
                        'key' => 'media_support',
                        'label' => __('MMS Support', 'wp-sms'),
                        'type' => 'html',
                        'description' => Gateway::mms_status() ?: '<span class="wpsms-indicator__status inactive">
    <svg viewBox="0 0 6 6" xmlns="http://www.w3.org/2000/svg">
        <circle cx="3" cy="2" r="1" stroke-width="2"></circle>
    </svg>
    <span>Unavailable
</span>'
                    ]),
                ]
            ]),
            new Section([
                'id' => 'account_balance_visibility',
                'title' => __('Balance Display', 'wp-sms'),
                'subtitle' => __('Choose where to show your account balance.', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'account_credit_in_menu',
                        'label' => __('Show Balance in Admin Menu', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Show your balance in the sidebar menu.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'account_credit_in_sendsms',
                        'label' => __('Show Balance on Send SMS Page', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Show your balance on the Send SMS screen.', 'wp-sms')
                    ]),
                ]
            ]),
            new Section([
                'id' => 'sms_dispatch_optimization',
                'title' => __('Sending and Numbers', 'wp-sms'),
                'subtitle' => __('Choose how to send messages and how to handle numbers.', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'sms_delivery_method',
                        'label' => __('Delivery Method', 'wp-sms'),
                        'type' => 'select',
                        'description' => __('Choose how messages are sent. Large lists are queued automatically when recipients exceed 20.', 'wp-sms'),
                        'options' => [
                            'api_direct_send' => __('Send Instantly', 'wp-sms'),
                            'api_async_send' => __('Schedule Send', 'wp-sms'),
                            'api_queued_send' => __('Queue for Bulk', 'wp-sms'),
                        ]
                    ]),
                    new Field([
                        'key' => 'send_unicode',
                        'label' => __('Unicode Messages', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Allow non-Latin characters such as Persian or Arabic. This reduces the characters allowed per SMS.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'clean_numbers',
                        'label' => __('Normalize Numbers', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Remove spaces and common separators before sending.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'send_only_local_numbers',
                        'label' => __('Limit Delivery by Country', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Send only to selected countries to avoid international fees.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'only_local_numbers_countries',
                        'label' => __('Allowed Countries', 'wp-sms'),
                        'type' => 'multiselect',
                        'description' => __('Only numbers from these countries will receive messages.', 'wp-sms'),
                        'options' => $this->getCountriesOptions(),
                        'show_if' => ['send_only_local_numbers' => true]
                    ]),
                ]
            ]),
        ];
    }

    /**
     * Get dynamic gateway setup fields based on the selected gateway
     */
    private function getGatewaySetupFields(): array {
        $fields = [
            new Field([
                'key' => 'gateway_name',
                'label' => __('Gateway Provider', 'wp-sms'),
                'type' => 'advancedselect',
                'description' => __('Select the SMS gateway you want to use.', 'wp-sms'),
                'options' => Gateway::gateway(),
                'auto_save_and_refresh' => true
            ]),
            new Field([
                'key' => 'gateway_help',
                'label' => __('Gateway Guide', 'wp-sms'),
                'type' => 'html',
                'description' => __('See setup steps and tips for your selected gateway.', 'wp-sms'),
                'options' => Gateway::help()
            ])
        ];

        // Get the current gateway settings using the existing filter
        $gatewaySettings = $this->getFilteredGatewaySettings();
        
        // Add dynamic gateway fields (hidden from UI since React handles them)
        foreach ($gatewaySettings as $key => $setting) {
            if (in_array($key, ['gateway_name', 'gateway_help'])) {
                continue; // Skip these as they're already added
            }

            $fieldConfig = [
                'key' => $key,
                'label' => $setting['name'] ?? $key,
                'type' => $setting['type'] ?? 'text',
                'description' => $setting['description'] ?? '',
                'hidden' => false, // Hide from UI since React handles these dynamically
            ];

            // Add options if available
            if (!empty($setting['options'])) {
                $fieldConfig['options'] = $setting['options'];
            }

            // Add default value for sender_id
            if ($key === 'gateway_sender_id') {
                $fieldConfig['default'] = Gateway::from();
            }

            $fields[] = new Field($fieldConfig);
        }

        return $fields;
    }

    /**
     * Get filtered gateway settings using the existing filter system
     */
    private function getFilteredGatewaySettings(): array {
        // Default gateway settings
        $defaultSettings = [
            'gateway_username' => [
                'name' => __('API Username', 'wp-sms'),
                'type' => 'text',
                'description' => __('Your API username from the gateway dashboard.', 'wp-sms'),
            ],
            'gateway_password' => [
                'name' => __('API Password', 'wp-sms'),
                'type' => 'text',
                'description' => __('Your API password or token if required.', 'wp-sms'),
            ],
            'gateway_sender_id' => [
                'name' => __('Sender ID or Number', 'wp-sms'),
                'type' => 'text',
                'description' => __('The number or approved sender ID used for outgoing SMS.', 'wp-sms'),
            ],
            'gateway_key' => [
                'name' => __('API Key', 'wp-sms'),
                'type' => 'text',
                'description' => __('Your API key from the gateway dashboard.', 'wp-sms'),
            ],
        ];

        // Apply the existing filter to get gateway-specific settings
        return apply_filters('wp_sms_gateway_settings', $defaultSettings);
    }

    private function getCountriesOptions(): array {
        $countries = wp_sms_countries()->getCountriesMerged();
        $options = [];
        
        foreach ($countries as $key => $value) {
            $options[] = [$key => $value];
        }
        
        return $options;
    }

    public function getFields(): array {
        // Legacy method - return all fields from all sections for backward compatibility
        $allFields = [];
        foreach ($this->getSections() as $section) {
            $allFields = array_merge($allFields, $section->getFields());
        }
        return $allFields;
    }
}
