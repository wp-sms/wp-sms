<?php

namespace WSms\Privacy;

use WSms\Contact\ContactRepository;
use WSms\Database\Connection;
use WSms\Exception\NotFoundException;

defined('ABSPATH') || exit;

class PrivacyRequestService
{
    private const ERASED_PLACEHOLDER = '[erased]';

    public function __construct(
        private readonly ContactRepository $contacts,
        private readonly Connection $db,
    ) {
    }

    /**
     * Look up all data associated with an identifier (email or phone).
     *
     * @return array{contacts: array, wp_user: array|null, message_log_count: int, auth_log_count: int}
     */
    public function lookup(string $identifier): array
    {
        $identifier = $this->normalizeIdentifier($identifier);
        $contacts = $this->findContacts($identifier);
        $wpUser = $this->findWpUser($identifier, $contacts);

        $recipients = $this->collectRecipients($identifier, $contacts);
        $messageLogCount = $this->countMessageLogs($recipients);
        $authLogCount = $wpUser ? $this->countAuthLogs($wpUser->ID) : 0;

        $enriched = array_map(function (array $contact) {
            $contact['tags'] = $this->contacts->getTags($contact['id']);
            return $contact;
        }, $contacts);

        return [
            'contacts'          => $enriched,
            'wp_user'           => $this->serializeWpUser($wpUser),
            'message_log_count' => $messageLogCount,
            'auth_log_count'    => $authLogCount,
        ];
    }

    /**
     * Export all data for the identifier as a JSON file.
     *
     * @return array{url: string, filename: string}
     */
    public function export(string $identifier): array
    {
        $identifier = $this->normalizeIdentifier($identifier);
        $contacts = $this->findContacts($identifier);
        $wpUser = $this->findWpUser($identifier, $contacts);

        if (empty($contacts) && !$wpUser) {
            throw new NotFoundException('No data found for this identifier.');
        }

        $recipients = $this->collectRecipients($identifier, $contacts);

        $enriched = array_map(function (array $contact) {
            $contact['tags'] = $this->contacts->getTags($contact['id']);
            return $contact;
        }, $contacts);

        $exportData = [
            'generated_at'   => gmdate('Y-m-d\TH:i:s\Z'),
            'identifier'     => $identifier,
            'contacts'       => $enriched,
            'wp_user'        => $this->serializeWpUser($wpUser),
            'message_logs'   => $this->getMessageLogs($recipients),
            'auth_log_count' => $wpUser ? $this->countAuthLogs($wpUser->ID) : 0,
        ];

        $uploadDir = wp_upload_dir();
        $exportDir = $uploadDir['basedir'] . '/wsms-exports';
        wp_mkdir_p($exportDir);

        $htaccessPath = $exportDir . '/.htaccess';
        if (!file_exists($htaccessPath)) {
            file_put_contents($htaccessPath, "deny from all\n");
        }
        $indexPath = $exportDir . '/index.php';
        if (!file_exists($indexPath)) {
            file_put_contents($indexPath, "<?php\n// Silence is golden.\n");
        }

        $filename = 'privacy-export-' . gmdate('Y-m-d-His') . '-' . wp_generate_password(8, false) . '.json';
        $filePath = $exportDir . '/' . $filename;

        file_put_contents($filePath, wp_json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        wp_schedule_single_event(time() + HOUR_IN_SECONDS, 'wsms_cleanup_export_file', [$filePath]);

        return [
            'url'      => $uploadDir['baseurl'] . '/wsms-exports/' . $filename,
            'filename' => $filename,
        ];
    }

    /**
     * Erase all data for the identifier.
     *
     * @return array{contacts_removed: int, message_logs_anonymized: int, auth_logs_removed: int}
     */
    public function erase(string $identifier): array
    {
        $identifier = $this->normalizeIdentifier($identifier);
        $contacts = $this->findContacts($identifier);
        $wpUser = $this->findWpUser($identifier, $contacts);

        $contactsRemoved = 0;

        foreach ($contacts as $contact) {
            $this->db->delete(Connection::TABLE_CONTACT_TAG, ['contact_id' => $contact['id']]);

            if (!empty($contact['email'])) {
                $this->db->delete(Connection::TABLE_VERIFICATIONS, ['identifier' => $contact['email']]);
            }
            if (!empty($contact['phone'])) {
                $this->db->delete(Connection::TABLE_VERIFICATIONS, ['identifier' => $contact['phone']]);
            }

            $this->db->delete(Connection::TABLE_CONTACTS, ['id' => $contact['id']]);
            $contactsRemoved++;
        }

        $messageLogsAnonymized = 0;
        $recipients = $this->collectRecipients($identifier, $contacts);
        if (!empty($recipients)) {
            $table = $this->db->table(Connection::TABLE_MESSAGE_LOGS);
            $placeholders = self::placeholders($recipients);

            $messageLogsAnonymized = (int) $this->db->query(
                "UPDATE {$table} SET recipient = %s WHERE recipient IN ({$placeholders}) AND recipient != %s",
                ...array_merge([self::ERASED_PLACEHOLDER], $recipients, [self::ERASED_PLACEHOLDER]),
            );
        }

        $authLogsRemoved = 0;
        if ($wpUser) {
            $authLogsRemoved = (int) $this->db->delete(
                Connection::TABLE_AUTH_LOGS,
                ['user_id' => $wpUser->ID],
            );
        }

        return [
            'contacts_removed'        => $contactsRemoved,
            'message_logs_anonymized' => $messageLogsAnonymized,
            'auth_logs_removed'       => $authLogsRemoved,
        ];
    }

    private function normalizeIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            throw new \InvalidArgumentException('Identifier is required.');
        }

        return $identifier;
    }

    private function isEmail(string $identifier): bool
    {
        return str_contains($identifier, '@');
    }

    /**
     * @return array[]
     */
    private function findContacts(string $identifier): array
    {
        if ($this->isEmail($identifier)) {
            $contact = $this->contacts->findByEmail($identifier);
            return $contact ? [$contact] : [];
        }

        return $this->contacts->findAllByPhone($identifier);
    }

    private function findWpUser(string $identifier, array $contacts): ?\WP_User
    {
        foreach ($contacts as $contact) {
            if (!empty($contact['wp_user_id'])) {
                $user = get_user_by('id', $contact['wp_user_id']);
                if ($user) {
                    return $user;
                }
            }
        }

        // Fallback: email identifier may match a WP user without a linked contact.
        if ($this->isEmail($identifier)) {
            $user = get_user_by('email', $identifier);
            if ($user) {
                return $user;
            }
        }

        return null;
    }

    private function serializeWpUser(?\WP_User $user): ?array
    {
        if (!$user) {
            return null;
        }

        return [
            'id'           => $user->ID,
            'display_name' => $user->display_name,
            'email'        => $user->user_email,
        ];
    }

    /**
     * @return string[]
     */
    private function collectRecipients(string $identifier, array $contacts): array
    {
        $recipients = [$identifier];

        foreach ($contacts as $contact) {
            if (!empty($contact['email'])) {
                $recipients[] = $contact['email'];
            }
            if (!empty($contact['phone'])) {
                $recipients[] = $contact['phone'];
            }
        }

        return array_values(array_unique($recipients));
    }

    private function countMessageLogs(array $recipients): int
    {
        if (empty($recipients)) {
            return 0;
        }

        $table = $this->db->table(Connection::TABLE_MESSAGE_LOGS);
        $placeholders = self::placeholders($recipients);

        return (int) $this->db->getVar(
            "SELECT COUNT(*) FROM {$table} WHERE recipient IN ({$placeholders}) AND recipient != %s",
            ...array_merge($recipients, [self::ERASED_PLACEHOLDER]),
        );
    }

    private function countAuthLogs(int $userId): int
    {
        $table = $this->db->table(Connection::TABLE_AUTH_LOGS);

        return (int) $this->db->getVar(
            "SELECT COUNT(*) FROM {$table} WHERE user_id = %d",
            $userId,
        );
    }

    private function getMessageLogs(array $recipients): array
    {
        if (empty($recipients)) {
            return [];
        }

        $table = $this->db->table(Connection::TABLE_MESSAGE_LOGS);
        $placeholders = self::placeholders($recipients);

        return $this->db->getResults(
            "SELECT id, channel, recipient, status, type, sent_at, created_at FROM {$table} WHERE recipient IN ({$placeholders}) AND recipient != %s ORDER BY created_at DESC",
            ...array_merge($recipients, [self::ERASED_PLACEHOLDER]),
        );
    }

    private static function placeholders(array $items): string
    {
        return implode(',', array_fill(0, count($items), '%s'));
    }
}
