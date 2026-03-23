<?php

namespace WSms\Contact;

use WSms\Dependencies\Symfony\Component\Uid\Ulid;
use WSms\Contact\Contracts\ContactRepositoryInterface;

defined('ABSPATH') || exit;

class ContactRepository implements ContactRepositoryInterface
{
    public function create(array $data, bool $suppressEvents = false): string
    {
        global $wpdb;

        $id = (string) new Ulid();
        $now = current_time('mysql');

        $wpdb->insert($wpdb->prefix . 'wsms_contacts', [
            'id'             => $id,
            'email'          => isset($data['email']) ? strtolower($data['email']) : null,
            'phone'          => isset($data['phone']) ? self::normalizePhone($data['phone']) : null,
            'first_name'     => $data['first_name'] ?? null,
            'last_name'      => $data['last_name'] ?? null,
            'wp_user_id'     => $data['wp_user_id'] ?? null,
            'status'         => $data['status'] ?? 'subscribed',
            'email_verified' => !empty($data['email_verified']) ? 1 : 0,
            'phone_verified' => !empty($data['phone_verified']) ? 1 : 0,
            'custom_fields'  => isset($data['custom_fields']) ? wp_json_encode($data['custom_fields']) : null,
            'source'         => $data['source'] ?? 'manual',
            'source_ref'     => $data['source_ref'] ?? null,
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);

        if (!$suppressEvents) {
            do_action('wsms_contact_created', $id, $data);
        }

        return $id;
    }

    public function update(string $id, array $data): bool
    {
        global $wpdb;

        // Pre-fetch current row fields needed for change detection (status, email, phone).
        $needsCurrentRow = array_key_exists('status', $data)
            || array_key_exists('email', $data)
            || array_key_exists('phone', $data);

        $currentRow = null;
        $oldStatus = null;
        if ($needsCurrentRow) {
            $currentRow = $wpdb->get_row($wpdb->prepare(
                "SELECT status, email, phone FROM {$wpdb->prefix}wsms_contacts WHERE id = %s",
                $id,
            ), ARRAY_A);
            $oldStatus = $currentRow['status'] ?? null;
        }

        $update = ['updated_at' => current_time('mysql')];

        foreach (['email', 'phone', 'first_name', 'last_name', 'wp_user_id', 'status', 'source', 'source_ref', 'opted_out_at', 'email_verified', 'phone_verified'] as $field) {
            if (array_key_exists($field, $data)) {
                if ($field === 'email' && $data[$field] !== null) {
                    $update[$field] = strtolower($data[$field]);
                } elseif ($field === 'phone' && $data[$field] !== null) {
                    $update[$field] = self::normalizePhone($data[$field]);
                } elseif ($field === 'email_verified' || $field === 'phone_verified') {
                    $update[$field] = $data[$field] ? 1 : 0;
                } else {
                    $update[$field] = $data[$field];
                }
            }
        }

        // Auto-reset verification flags when identifier changes.
        if ($currentRow !== null) {
            if (array_key_exists('email', $data)) {
                $newEmail = $update['email'] ?? null;
                if ($newEmail !== ($currentRow['email'] ?? null) && !array_key_exists('email_verified', $data)) {
                    $update['email_verified'] = 0;
                }
            }
            if (array_key_exists('phone', $data)) {
                $newPhone = $update['phone'] ?? null;
                if ($newPhone !== ($currentRow['phone'] ?? null) && !array_key_exists('phone_verified', $data)) {
                    $update['phone_verified'] = 0;
                }
            }
        }

        if (isset($data['custom_fields'])) {
            $update['custom_fields'] = wp_json_encode($data['custom_fields']);
        }

        $result = (bool) $wpdb->update($wpdb->prefix . 'wsms_contacts', $update, ['id' => $id]);

        if ($result) {
            if ($oldStatus !== null && $oldStatus !== ($data['status'] ?? $oldStatus)) {
                do_action('wsms_contact_status_changed', $id, $oldStatus, $data['status']);
            }
            do_action('wsms_contact_updated', $id, $data);
        }

        return $result;
    }

    public function find(string $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}wsms_contacts WHERE id = %s", $id),
            ARRAY_A,
        );
        return $row ? self::decodeRow($row) : null;
    }

    public function findByEmail(string $email): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}wsms_contacts WHERE email = %s", strtolower($email)),
            ARRAY_A,
        );
        return $row ? self::decodeRow($row) : null;
    }

    public function findByPhone(string $phone): ?array
    {
        global $wpdb;
        $normalized = self::normalizePhone($phone);
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}wsms_contacts WHERE phone = %s", $normalized),
            ARRAY_A,
        );
        return $row ? self::decodeRow($row) : null;
    }

    public function findAllByPhone(string $phone): array
    {
        global $wpdb;
        $normalized = self::normalizePhone($phone);
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}wsms_contacts WHERE phone = %s", $normalized),
            ARRAY_A,
        ) ?: [];

        return array_map([self::class, 'decodeRow'], $rows);
    }

    public function findByWpUser(int $userId): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}wsms_contacts WHERE wp_user_id = %d", $userId),
            ARRAY_A,
        );
        return $row ? self::decodeRow($row) : null;
    }

    public function findAll(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wsms_contacts';

        [$where, $params] = self::buildWhereClause($filters, $wpdb);

        $params[] = $limit;
        $params[] = $offset;

        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d", ...$params),
            ARRAY_A,
        ) ?: [];

        return array_map([self::class, 'decodeRow'], $rows);
    }

    public function count(array $filters = []): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wsms_contacts';

        [$where, $params] = self::buildWhereClause($filters, $wpdb);

        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";

        return (int) (empty($params) ? $wpdb->get_var($sql) : $wpdb->get_var($wpdb->prepare($sql, ...$params)));
    }

    public function delete(string $id): bool
    {
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'wsms_contact_tag', ['contact_id' => $id]);
        return (bool) $wpdb->delete($wpdb->prefix . 'wsms_contacts', ['id' => $id]);
    }

    public function addTag(string $contactId, string $tagId): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wsms_contact_tag';

        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$table} (contact_id, tag_id, created_at) VALUES (%s, %s, %s)",
            $contactId,
            $tagId,
            current_time('mysql'),
        ));

        // Only fire event if a new row was actually inserted
        if ($wpdb->rows_affected > 0) {
            do_action('wsms_contact_tagged', $contactId, $tagId);
        }
    }

    public function removeTag(string $contactId, string $tagId): void
    {
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'wsms_contact_tag', [
            'contact_id' => $contactId,
            'tag_id'     => $tagId,
        ]);
    }

    public function getTags(string $contactId): array
    {
        global $wpdb;
        $tagsTable = $wpdb->prefix . 'wsms_tags';
        $pivotTable = $wpdb->prefix . 'wsms_contact_tag';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t.* FROM {$tagsTable} t INNER JOIN {$pivotTable} ct ON t.id = ct.tag_id WHERE ct.contact_id = %s",
                $contactId,
            ),
            ARRAY_A,
        ) ?: [];
    }

    public function findByTag(string $tagId, int $limit = 50, int $offset = 0): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wsms_contacts';
        $pivotTable = $wpdb->prefix . 'wsms_contact_tag';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT c.* FROM {$table} c INNER JOIN {$pivotTable} ct ON c.id = ct.contact_id WHERE ct.tag_id = %s ORDER BY c.created_at DESC LIMIT %d OFFSET %d",
                $tagId,
                $limit,
                $offset,
            ),
            ARRAY_A,
        ) ?: [];

        return array_map([self::class, 'decodeRow'], $rows);
    }

    public function countByTag(string $tagId): int
    {
        global $wpdb;
        $pivotTable = $wpdb->prefix . 'wsms_contact_tag';

        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$pivotTable} WHERE tag_id = %s", $tagId),
        );
    }

    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'wsms_contacts';
        $ph = self::placeholders($ids);

        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id IN ({$ph})", ...$ids),
            ARRAY_A,
        ) ?: [];

        return array_map([self::class, 'decodeRow'], $rows);
    }

    public function bulkDelete(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'wsms_contacts';
        $pivotTable = $wpdb->prefix . 'wsms_contact_tag';
        $ph = self::placeholders($ids);

        $wpdb->query($wpdb->prepare("DELETE FROM {$pivotTable} WHERE contact_id IN ({$ph})", ...$ids));

        return (int) $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE id IN ({$ph})", ...$ids));
    }

    public function bulkUpdateStatus(array $ids, string $status): int
    {
        if (empty($ids)) {
            return 0;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'wsms_contacts';
        $ph = self::placeholders($ids);
        $now = current_time('mysql');

        return (int) $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET status = %s, updated_at = %s WHERE id IN ({$ph})",
                $status,
                $now,
                ...$ids,
            ),
        );
    }

    public static function normalizePhone(string $phone): string
    {
        return preg_replace('/[\s\-\(\)\.]+/', '', $phone);
    }

    private static function placeholders(array $items): string
    {
        return implode(',', array_fill(0, count($items), '%s'));
    }

    private static function buildWhereClause(array $filters, object $wpdb): array
    {
        $where = '1=1';
        $params = [];

        if (!empty($filters['status'])) {
            $where .= ' AND status = %s';
            $params[] = $filters['status'];
        }
        if (!empty($filters['source'])) {
            $where .= ' AND source = %s';
            $params[] = $filters['source'];
        }
        if (!empty($filters['source_ref'])) {
            $where .= ' AND source_ref = %s';
            $params[] = $filters['source_ref'];
        }
        if (isset($filters['email_verified'])) {
            $where .= ' AND email_verified = %d';
            $params[] = $filters['email_verified'] ? 1 : 0;
        }
        if (isset($filters['phone_verified'])) {
            $where .= ' AND phone_verified = %d';
            $params[] = $filters['phone_verified'] ? 1 : 0;
        }
        if (!empty($filters['search'])) {
            $where .= ' AND (email LIKE %s OR phone LIKE %s OR first_name LIKE %s OR last_name LIKE %s)';
            $like = '%' . $wpdb->esc_like($filters['search']) . '%';
            $params = array_merge($params, [$like, $like, $like, $like]);
        }

        return [$where, $params];
    }

    private static function decodeRow(array $row): array
    {
        if (isset($row['custom_fields']) && is_string($row['custom_fields'])) {
            $row['custom_fields'] = json_decode($row['custom_fields'], true) ?? [];
        }
        if (array_key_exists('email_verified', $row)) {
            $row['email_verified'] = (bool) $row['email_verified'];
        }
        if (array_key_exists('phone_verified', $row)) {
            $row['phone_verified'] = (bool) $row['phone_verified'];
        }
        return $row;
    }
}
