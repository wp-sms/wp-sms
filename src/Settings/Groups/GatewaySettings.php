<?php

namespace WP_SMS\Settings\Groups;

use WP_SMS\Gateway;
use WP_SMS\Settings\Field;
use WP_SMS\Settings\Abstracts\AbstractSettingGroup;

class GatewaySettings extends AbstractSettingGroup {
    public function getName(): string {
        return 'gateway';
    }

    public function getLabel(): string {
        return 'Gateway Settings';
    }

    public function getFields(): array {
        return [
            new Field([
                'key'         => 'gateway_title',
                'type'        => 'header',
                'label'       => 'SMS Gateway Setup',
                'group_label' => 'Gateway',
            ]),
            new Field([
                'key'         => 'gateway_name',
                'type'        => 'advancedselect',
                'label'       => 'Choose the Gateway',
                'description' => 'Select your preferred SMS Gateway to send messages.',
                'options'     => Gateway::gateway(),
                'group_label' => 'Gateway',
            ]),
            new Field([
                'key'         => 'gateway_help',
                'type'        => 'html',
                'label'       => 'Gateway Guide',
                'options'     => Gateway::help(),
                'group_label' => 'Gateway',
            ]),
            new Field([
                'key'         => 'gateway_username',
                'type'        => 'text',
                'label'       => 'API Username',
                'description' => 'Enter API username of gateway',
                'group_label' => 'Gateway',
            ]),
            new Field([
                'key'         => 'gateway_password',
                'type'        => 'text',
                'label'       => 'API Password',
                'description' => 'Enter API password of gateway',
                'group_label' => 'Gateway',
            ]),
            new Field([
                'key'         => 'gateway_sender_id',
                'type'        => 'text',
                'label'       => 'Sender ID/Number',
                'description' => 'Sender number or sender ID',
                'default'     => Gateway::from(),
                'group_label' => 'Gateway',
            ]),
            new Field([
                'key'         => 'gateway_key',
                'type'        => 'text',
                'label'       => 'API Key',
                'description' => 'Enter API key of gateway',
                'group_label' => 'Gateway',
            ]),
            new Field([
                'key'         => 'gateway_status_title',
                'type'        => 'header',
                'label'       => 'Gateway Overview',
                'group_label' => 'Gateway',
            ]),
            new Field([
                'key'         => 'account_credit',
                'type'        => 'html',
                'label'       => 'Status',
                'description' => 'Dynamic gateway status',
                'options'     => Gateway::status(),
                'group_label' => 'Gateway',
            ]),
            new Field([
                'key'         => 'account_response',
                'type'        => 'html',
                'label'       => 'Balance / Credit',
                'description' => 'Dynamic gateway balance',
                'options'     => Gateway::response(),
                'group_label' => 'Gateway',
            ]),
            new Field([
                'key'         => 'incoming_message',
                'type'        => 'html',
                'label'       => 'Incoming Message',
                'description' => 'Indicates support for receiving SMS',
                'options'     => Gateway::incoming_message_status(),
                'group_label' => 'Gateway',
            ]),
            new Field([
                'key'         => 'bulk_send',
                'type'        => 'html',
                'label'       => 'Send Bulk SMS',
                'description' => 'Indicates support for sending SMS in bulk',
                'options'     => Gateway::bulk_status(),
                'group_label' => 'Gateway',
            ]),
            new Field([
                'key'         => 'media_support',
                'type'        => 'html',
                'label'       => 'Send MMS',
                'description' => 'Indicates support for multimedia messages (MMS)',
                'options'     => Gateway::mms_status(),
                'group_label' => 'Gateway',
            ]),
            new Field([
                'key'         => 'account_credit_title',
                'type'        => 'header',
                'label'       => 'Account Balance Visibility',
                'group_label' => 'Gateway',
            ]),
            new Field([
                'key'         => 'account_credit_in_menu',
                'type'        => 'checkbox',
                'label'       => 'Admin Menu Display',
                'description' => 'Shows account credit in the admin menu.',
                'group_label' => 'Gateway',
            ]),
            new Field([
                'key'         => 'account_credit_in_sendsms',
                'type'        => 'checkbox',
                'label'       => 'SMS Page Display',
                'description' => 'Displays account credit on the SMS sending page.',
                'group_label' => 'Gateway',
            ]),
            new Field([
                'key'         => 'message_title',
                'type'        => 'header',
                'label'       => 'SMS Dispatch & Number Optimization',
                'group_label' => 'Gateway',
            ]),
            new Field([
                'key'         => 'sms_delivery_method',
                'type'        => 'select',
                'label'       => 'Delivery Method',
                'description' => 'Select the dispatch method for SMS messages: instant send via API, delayed send at set times, or batch send for large recipient lists. For lists exceeding 20 recipients, batch sending is automatically selected.',
                'options'     => [
                    'api_direct_send' => 'Send SMS Instantly: Activates immediate dispatch of messages via API upon request.',
                    'api_async_send'  => 'Scheduled SMS Delivery: Configures API to send messages at predetermined times.',
                    'api_queued_send' => 'Batch SMS Queue: Lines up messages for grouped sending, enhancing efficiency for bulk dispatch.',
                ],
                'group_label' => 'Gateway',
            ]),
            new Field([
                'key'         => 'send_unicode',
                'type'        => 'checkbox',
                'label'       => 'Unicode Messaging',
                'description' => 'Send messages in languages that use non-English characters, like Persian, Arabic, Chinese, or Cyrillic.',
                'group_label' => 'Gateway',
            ]),
            new Field([
                'key'         => 'clean_numbers',
                'type'        => 'checkbox',
                'label'       => 'Number Formatting',
                'description' => 'Strips spaces from phone numbers before sending.',
                'group_label' => 'Gateway',
            ]),
            new Field([
                'key'         => 'send_only_local_numbers',
                'type'        => 'checkbox',
                'label'       => 'Restrict to Local Numbers',
                'description' => 'Send messages to numbers within the same country to avoid international fees.',
                'group_label' => 'Gateway',
            ]),
            new Field([
                'key'         => 'only_local_numbers_countries',
                'type'        => 'multiselect',
                'label'       => 'Allowed Countries for SMS',
                'description' => 'Specify countries allowed for SMS delivery. Only listed countries will receive messages.',
                'show_if'     => ['send_only_local_numbers' => true],
                'options'     => wp_sms_countries()->getCountriesMerged(),
                'group_label' => 'Gateway',
            ]),
        ];
    }
}
