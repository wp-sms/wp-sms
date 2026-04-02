<?php

namespace WSms\Privacy;

use WSms\Contact\ContactRepository;
use WSms\Database\Connection;
use WSms\Support\DateFormatter;

defined('ABSPATH') || exit;

class ContactDataExporter
{
    public function __construct(
        private readonly ContactRepository $contacts,
        private readonly Connection $db,
    ) {
    }

    public function export(string $email, int $page): array
    {
        $contact = $this->contacts->findByEmail($email);

        if (!$contact) {
            return ['data' => [], 'done' => true];
        }

        $tags = $this->contacts->getTags($contact['id']);
        $tagNames = array_column($tags, 'name');

        $customFields = [];
        if (!empty($contact['custom_fields'])) {
            $decoded = is_string($contact['custom_fields'])
                ? json_decode($contact['custom_fields'], true)
                : $contact['custom_fields'];
            if (is_array($decoded)) {
                foreach ($decoded as $key => $value) {
                    $customFields[] = [
                        'name'  => ucwords(str_replace('_', ' ', $key)),
                        'value' => is_scalar($value) ? (string) $value : wp_json_encode($value),
                    ];
                }
            }
        }

        $channelOptOuts = '';
        if (!empty($contact['channel_opt_outs'])) {
            $decoded = is_string($contact['channel_opt_outs'])
                ? json_decode($contact['channel_opt_outs'], true)
                : $contact['channel_opt_outs'];
            if (is_array($decoded) && !empty($decoded)) {
                $channelOptOuts = implode(', ', array_keys($decoded));
            }
        }

        $data = array_filter([
            ['name' => __('First Name', 'wp-sms'), 'value' => $contact['first_name'] ?? ''],
            ['name' => __('Last Name', 'wp-sms'), 'value' => $contact['last_name'] ?? ''],
            ['name' => __('Email', 'wp-sms'), 'value' => $contact['email'] ?? ''],
            ['name' => __('Phone', 'wp-sms'), 'value' => $contact['phone'] ?? ''],
            ['name' => __('Status', 'wp-sms'), 'value' => $contact['status'] ?? ''],
            ['name' => __('Source', 'wp-sms'), 'value' => $contact['source'] ?? ''],
            ['name' => __('Source Reference', 'wp-sms'), 'value' => $contact['source_ref'] ?? ''],
            ['name' => __('Tags', 'wp-sms'), 'value' => implode(', ', $tagNames)],
            ['name' => __('Channel Opt-outs', 'wp-sms'), 'value' => $channelOptOuts],
            ['name' => __('Subscribed At', 'wp-sms'), 'value' => DateFormatter::formatTimestamp($contact['created_at'] ?? '')],
        ], fn($item) => $item['value'] !== '');

        $data = array_merge($data, $customFields);

        return [
            'data' => [
                [
                    'group_id'    => 'wsms-contact',
                    'group_label' => __('WSMS Contact Data', 'wp-sms'),
                    'item_id'     => 'wsms-contact-' . $contact['id'],
                    'data'        => array_values($data),
                ],
            ],
            'done' => true,
        ];
    }

}
