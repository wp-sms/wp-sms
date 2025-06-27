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
                'title' => __('SMS Gateway Setup', 'wp-sms'),
                'subtitle' => __('Configure your SMS gateway provider settings', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'gateway_name',
                        'label' => __('Choose the Gateway', 'wp-sms'),
                        'type' => 'advancedselect',
                        'description' => __('Select your preferred SMS Gateway to send messages.', 'wp-sms'),
                        'options' => Gateway::gateway()
                    ]),
                    new Field([
                        'key' => 'gateway_help',
                        'label' => __('Gateway Guide', 'wp-sms'),
                        'type' => 'html',
                        'description' => '',
                        'options' => Gateway::help()
                    ]),
                    // Note: Gateway-specific fields will be loaded dynamically via REST API
                    // These are the default fields that will be shown when no gateway is selected
                    new Field([
                        'key' => 'gateway_username',
                        'label' => __('API Username', 'wp-sms'),
                        'type' => 'text',
                        'description' => __('Enter API username of gateway', 'wp-sms'),
                        'show_if' => ['gateway_name' => 'default']
                    ]),
                    new Field([
                        'key' => 'gateway_password',
                        'label' => __('API Password', 'wp-sms'),
                        'type' => 'text',
                        'description' => __('Enter API password of gateway', 'wp-sms'),
                        'show_if' => ['gateway_name' => 'default']
                    ]),
                    new Field([
                        'key' => 'gateway_sender_id',
                        'label' => __('Sender ID/Number', 'wp-sms'),
                        'type' => 'text',
                        'description' => __('Sender number or sender ID', 'wp-sms'),
                        'default' => Gateway::from(),
                        'show_if' => ['gateway_name' => 'default']
                    ]),
                    new Field([
                        'key' => 'gateway_key',
                        'label' => __('API Key', 'wp-sms'),
                        'type' => 'text',
                        'description' => __('Enter API key of gateway', 'wp-sms'),
                        'show_if' => ['gateway_name' => 'default']
                    ]),
                ]
            ]),
            new Section([
                'id' => 'gateway_overview',
                'title' => __('Gateway Overview', 'wp-sms'),
                'subtitle' => __('View your gateway status and capabilities', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'account_credit',
                        'label' => __('Status', 'wp-sms'),
                        'type' => 'html',
                        'description' => Gateway::status() ?: '<span class="wpsms-indicator__status inactive">
    <svg viewBox="0 0 6 6" xmlns="http://www.w3.org/2000/svg">
        <circle cx="3" cy="2" r="1" stroke-width="2"></circle>
    </svg>
    <span><a href="https://wp-sms-pro.com/product/wp-sms-two-way" target="_blank">Not Available</a></span>
</span>'
                    ]),
                    new Field([
                        'key' => 'account_response',
                        'label' => __('Balance / Credit', 'wp-sms'),
                        'type' => 'html',
                        'description' => Gateway::response() ?: '<span class="wpsms-indicator__status inactive">
    <svg viewBox="0 0 6 6" xmlns="http://www.w3.org/2000/svg">
        <circle cx="3" cy="2" r="1" stroke-width="2"></circle>
    </svg>
    <span><a href="https://wp-sms-pro.com/product/wp-sms-two-way" target="_blank">Not Available</a></span>
</span>'
                    ]),
                    new Field([
                        'key' => 'incoming_message',
                        'label' => __('Incoming Message', 'wp-sms'),
                        'type' => 'html',
                        'description' => Gateway::incoming_message_status() ?: '<span class="wpsms-indicator__status inactive">
    <svg viewBox="0 0 6 6" xmlns="http://www.w3.org/2000/svg">
        <circle cx="3" cy="2" r="1" stroke-width="2"></circle>
    </svg>
    <span><a href="https://wp-sms-pro.com/product/wp-sms-two-way" target="_blank">Not Available</a></span>
</span>'
                    ]),
                    new Field([
                        'key' => 'bulk_send',
                        'label' => __('Send Bulk SMS', 'wp-sms'),
                        'type' => 'html',
                        'description' => Gateway::bulk_status() ?: '<span class="wpsms-indicator__status inactive">
    <svg viewBox="0 0 6 6" xmlns="http://www.w3.org/2000/svg">
        <circle cx="3" cy="2" r="1" stroke-width="2"></circle>
    </svg>
    <span><a href="https://wp-sms-pro.com/product/wp-sms-two-way" target="_blank">Not Available</a></span>
</span>'
                    ]),
                    new Field([
                        'key' => 'media_support',
                        'label' => __('Send MMS', 'wp-sms'),
                        'type' => 'html',
                        'description' => Gateway::mms_status() ?: '<span class="wpsms-indicator__status inactive">
    <svg viewBox="0 0 6 6" xmlns="http://www.w3.org/2000/svg">
        <circle cx="3" cy="2" r="1" stroke-width="2"></circle>
    </svg>
    <span><a href="https://wp-sms-pro.com/product/wp-sms-two-way" target="_blank">Not Available</a></span>
</span>'
                    ]),
                ]
            ]),
            new Section([
                'id' => 'account_balance_visibility',
                'title' => __('Account Balance Visibility', 'wp-sms'),
                'subtitle' => __('Configure where account credit information is displayed', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'account_credit_in_menu',
                        'label' => __('Admin Menu Display', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Shows account credit in the admin menu.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'account_credit_in_sendsms',
                        'label' => __('SMS Page Display', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Displays account credit on the SMS sending page.', 'wp-sms')
                    ]),
                ]
            ]),
            new Section([
                'id' => 'sms_dispatch_optimization',
                'title' => __('SMS Dispatch & Number Optimization', 'wp-sms'),
                'subtitle' => __('Configure SMS delivery methods and number handling', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'sms_delivery_method',
                        'label' => __('Delivery Method', 'wp-sms'),
                        'type' => 'select',
                        'description' => __('Select the dispatch method for SMS messages: instant send via API, delayed send at set times, or batch send for large recipient lists. For lists exceeding 20 recipients, batch sending is automatically selected.', 'wp-sms'),
                        'options' => [
                            'api_direct_send' => __('Send SMS Instantly: Activates immediate dispatch of messages via API upon request.', 'wp-sms'),
                            'api_async_send' => __('Scheduled SMS Delivery: Configures API to send messages at predetermined times.', 'wp-sms'),
                            'api_queued_send' => __('Batch SMS Queue: Lines up messages for grouped sending, enhancing efficiency for bulk dispatch.', 'wp-sms'),
                        ]
                    ]),
                    new Field([
                        'key' => 'send_unicode',
                        'label' => __('Unicode Messaging', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Send messages in languages that use non-English characters, like Persian, Arabic, Chinese, or Cyrillic.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'clean_numbers',
                        'label' => __('Number Formatting', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Strips spaces from phone numbers before sending.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'send_only_local_numbers',
                        'label' => __('Restrict to Local Numbers', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Send messages to numbers within the same country to avoid international fees.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'only_local_numbers_countries',
                        'label' => __('Allowed Countries for SMS', 'wp-sms'),
                        'type' => 'multiselect',
                        'description' => __('Specify countries allowed for SMS delivery. Only listed countries will receive messages.', 'wp-sms'),
                        'options' => $this->getCountriesOptions(),
                        'show_if' => ['send_only_local_numbers' => true]
                    ]),
                ]
            ]),
        ];
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
