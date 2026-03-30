<?php

namespace WSms\Privacy;

use WSms\Contact\ContactRepository;
use WSms\Database\Connection;

defined('ABSPATH') || exit;

class MessageLogHandler
{
    private const BATCH_SIZE = 100;

    public function __construct(
        private readonly ContactRepository $contacts,
        private readonly Connection $db,
    ) {
    }

    /**
     * Export message log entries for a given email.
     * Does NOT export body_preview to avoid re-exposing sensitive content.
     */
    public function export(string $email, int $page): array
    {
        $recipients = $this->resolveRecipients($email);

        if (empty($recipients)) {
            return ['data' => [], 'done' => true];
        }

        $table = $this->db->table(Connection::TABLE_MESSAGE_LOGS);
        $placeholders = implode(',', array_fill(0, count($recipients), '%s'));
        $offset = ($page - 1) * self::BATCH_SIZE;

        $rows = $this->db->getResults(
            "SELECT id, channel, status, type, sent_at, created_at FROM {$table} WHERE recipient IN ({$placeholders}) ORDER BY created_at DESC LIMIT %d OFFSET %d",
            ...array_merge($recipients, [self::BATCH_SIZE, $offset]),
        );

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'group_id'    => 'wsms-message-log',
                'group_label' => __('WSMS Message History', 'wp-sms'),
                'item_id'     => 'wsms-message-' . $row['id'],
                'data'        => [
                    ['name' => __('Date', 'wp-sms'), 'value' => $row['sent_at'] ?? $row['created_at']],
                    ['name' => __('Channel', 'wp-sms'), 'value' => $row['channel']],
                    ['name' => __('Type', 'wp-sms'), 'value' => $row['type']],
                    ['name' => __('Status', 'wp-sms'), 'value' => $row['status']],
                ],
            ];
        }

        return [
            'data' => $items,
            'done' => count($rows) < self::BATCH_SIZE,
        ];
    }

    /**
     * Anonymize message log recipients for a given email.
     * Does NOT delete rows — preserves aggregate campaign stats.
     */
    public function erase(string $email, int $page): array
    {
        $recipients = $this->resolveRecipients($email);

        if (empty($recipients)) {
            return [
                'items_removed'  => false,
                'items_retained' => false,
                'messages'       => [],
                'done'           => true,
            ];
        }

        $table = $this->db->table(Connection::TABLE_MESSAGE_LOGS);
        $placeholders = implode(',', array_fill(0, count($recipients), '%s'));

        $affected = $this->db->query(
            "UPDATE {$table} SET recipient = '[erased]' WHERE recipient IN ({$placeholders}) AND recipient != '[erased]' LIMIT %d",
            ...array_merge($recipients, [self::BATCH_SIZE]),
        );

        // Check if more rows remain.
        $remaining = (int) $this->db->getVar(
            "SELECT COUNT(*) FROM {$table} WHERE recipient IN ({$placeholders}) AND recipient != '[erased]'",
            ...$recipients,
        );

        return [
            'items_removed'  => $affected > 0,
            'items_retained' => false,
            'messages'       => [],
            'done'           => $remaining === 0,
        ];
    }

    /**
     * Resolve all identifiers (email + phone) that might appear as recipients.
     *
     * @return string[]
     */
    private function resolveRecipients(string $email): array
    {
        $recipients = [$email];

        $contact = $this->contacts->findByEmail($email);
        if ($contact && !empty($contact['phone'])) {
            $recipients[] = $contact['phone'];
        }

        return $recipients;
    }
}
