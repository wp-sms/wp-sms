<?php

namespace WSms\Contact;

use WSms\Database\Connection;
use WSms\Dependencies\Symfony\Component\Uid\Ulid;
use WSms\Contact\Contracts\ContactRepositoryInterface;
use WSms\Exception\NotFoundException;

defined('ABSPATH') || exit;

class ContactRepository implements ContactRepositoryInterface
{
    private const BULK_CHUNK_SIZE = 500;

    public function __construct(private readonly Connection $db) {}

    public function create(array $data, bool $suppressEvents = false): string
    {
        $id = (string) new Ulid();
        $now = current_time('mysql');

        $this->db->insert(Connection::TABLE_CONTACTS, [
            'id'               => $id,
            'email'            => isset($data['email']) ? strtolower($data['email']) : null,
            'phone'            => isset($data['phone']) ? self::normalizePhone($data['phone']) : null,
            'first_name'       => $data['first_name'] ?? null,
            'last_name'        => $data['last_name'] ?? null,
            'wp_user_id'       => $data['wp_user_id'] ?? null,
            'status'           => $data['status'] ?? 'subscribed',
            'email_verified'   => !empty($data['email_verified']) ? 1 : 0,
            'phone_verified'   => !empty($data['phone_verified']) ? 1 : 0,
            'channel_opt_outs' => isset($data['channel_opt_outs']) ? wp_json_encode($data['channel_opt_outs']) : null,
            'custom_fields'    => isset($data['custom_fields']) ? wp_json_encode($data['custom_fields']) : null,
            'source'           => $data['source'] ?? 'manual',
            'source_ref'       => $data['source_ref'] ?? null,
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        if (!$suppressEvents) {
            do_action('wsms_contact_created', $id, $data);
        }

        return $id;
    }

    public function update(string $id, array $data): bool
    {
        $t = $this->db->table(Connection::TABLE_CONTACTS);

        // Pre-fetch current row fields needed for change detection (status, email, phone).
        $needsCurrentRow = array_key_exists('status', $data)
            || array_key_exists('email', $data)
            || array_key_exists('phone', $data);

        $currentRow = null;
        $oldStatus = null;
        if ($needsCurrentRow) {
            $currentRow = $this->db->getRow(
                "SELECT status, email, phone FROM {$t} WHERE id = %s",
                $id,
            );
            $oldStatus = $currentRow['status'] ?? null;
        }

        $update = ['updated_at' => current_time('mysql')];

        foreach (['email', 'phone', 'first_name', 'last_name', 'wp_user_id', 'status', 'source', 'source_ref', 'email_verified', 'phone_verified'] as $field) {
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

        $result = (bool) $this->db->update(Connection::TABLE_CONTACTS, $update, ['id' => $id]);

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
        $t = $this->db->table(Connection::TABLE_CONTACTS);
        $row = $this->db->getRow("SELECT * FROM {$t} WHERE id = %s", $id);
        return $row ? self::decodeRow($row) : null;
    }

    public function findOrFail(string $id): array
    {
        $contact = $this->find($id);
        if ($contact === null) {
            throw NotFoundException::entity('Contact', $id);
        }
        return $contact;
    }

    public function findByEmail(string $email): ?array
    {
        $t = $this->db->table(Connection::TABLE_CONTACTS);
        $row = $this->db->getRow("SELECT * FROM {$t} WHERE email = %s", strtolower($email));
        return $row ? self::decodeRow($row) : null;
    }

    public function findByEmails(array $emails): array
    {
        $emails = array_filter(array_map('strtolower', $emails));
        if (empty($emails)) {
            return [];
        }

        $t = $this->db->table(Connection::TABLE_CONTACTS);
        $ph = self::placeholders($emails);
        $rows = $this->db->getResults("SELECT * FROM {$t} WHERE email IN ({$ph})", ...$emails);

        $keyed = [];
        foreach ($rows as $row) {
            $keyed[$row['email']] = self::decodeRow($row);
        }
        return $keyed;
    }

    public function findByPhone(string $phone): ?array
    {
        $t = $this->db->table(Connection::TABLE_CONTACTS);
        $normalized = self::normalizePhone($phone);
        $row = $this->db->getRow("SELECT * FROM {$t} WHERE phone = %s", $normalized);
        return $row ? self::decodeRow($row) : null;
    }

    public function findByPhones(array $phones): array
    {
        $phones = array_filter(array_map([self::class, 'normalizePhone'], $phones));
        if (empty($phones)) {
            return [];
        }

        $t = $this->db->table(Connection::TABLE_CONTACTS);
        $ph = self::placeholders($phones);
        $rows = $this->db->getResults("SELECT * FROM {$t} WHERE phone IN ({$ph})", ...$phones);

        $keyed = [];
        foreach ($rows as $row) {
            $keyed[$row['phone']] = self::decodeRow($row);
        }
        return $keyed;
    }

    public function findAllByPhone(string $phone): array
    {
        $t = $this->db->table(Connection::TABLE_CONTACTS);
        $normalized = self::normalizePhone($phone);
        $rows = $this->db->getResults("SELECT * FROM {$t} WHERE phone = %s", $normalized);

        return array_map([self::class, 'decodeRow'], $rows);
    }

    public function findByWpUser(int $userId): ?array
    {
        $t = $this->db->table(Connection::TABLE_CONTACTS);
        $row = $this->db->getRow("SELECT * FROM {$t} WHERE wp_user_id = %d", $userId);
        return $row ? self::decodeRow($row) : null;
    }

    public function findAll(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $t = $this->db->table(Connection::TABLE_CONTACTS);

        [$where, $params] = $this->buildWhereClause($filters);

        $params[] = $limit;
        $params[] = $offset;

        $rows = $this->db->getResults("SELECT * FROM {$t} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d", ...$params);

        return array_map([self::class, 'decodeRow'], $rows);
    }

    public function count(array $filters = []): int
    {
        $t = $this->db->table(Connection::TABLE_CONTACTS);

        [$where, $params] = $this->buildWhereClause($filters);

        $sql = "SELECT COUNT(*) FROM {$t} WHERE {$where}";

        return (int) $this->db->getVar($sql, ...$params);
    }

    public function delete(string $id): bool
    {
        $this->db->delete(Connection::TABLE_CONTACT_TAG, ['contact_id' => $id]);
        return (bool) $this->db->delete(Connection::TABLE_CONTACTS, ['id' => $id]);
    }

    public function addTag(string $contactId, string $tagId): void
    {
        $t = $this->db->table(Connection::TABLE_CONTACT_TAG);

        $this->db->query(
            "INSERT IGNORE INTO {$t} (contact_id, tag_id, created_at) VALUES (%s, %s, %s)",
            $contactId,
            $tagId,
            current_time('mysql'),
        );

        // Only fire event if a new row was actually inserted
        if ($this->db->rowsAffected() > 0) {
            do_action('wsms_contact_tagged', $contactId, $tagId);
        }
    }

    public function removeTag(string $contactId, string $tagId): void
    {
        $this->db->delete(Connection::TABLE_CONTACT_TAG, [
            'contact_id' => $contactId,
            'tag_id'     => $tagId,
        ]);
    }

    public function getTags(string $contactId): array
    {
        $tagsTable = $this->db->table(Connection::TABLE_TAGS);
        $pivotTable = $this->db->table(Connection::TABLE_CONTACT_TAG);

        return $this->db->getResults(
            "SELECT t.* FROM {$tagsTable} t INNER JOIN {$pivotTable} ct ON t.id = ct.tag_id WHERE ct.contact_id = %s",
            $contactId,
        );
    }

    public function getTagsForContacts(array $contactIds): array
    {
        if (empty($contactIds)) {
            return [];
        }

        $tagsTable = $this->db->table(Connection::TABLE_TAGS);
        $pivotTable = $this->db->table(Connection::TABLE_CONTACT_TAG);
        $ph = self::placeholders($contactIds);

        $rows = $this->db->getResults(
            "SELECT ct.contact_id, t.id, t.name, t.slug, t.color FROM {$tagsTable} t INNER JOIN {$pivotTable} ct ON t.id = ct.tag_id WHERE ct.contact_id IN ({$ph})",
            ...$contactIds,
        );

        $grouped = [];
        foreach ($rows as $row) {
            $contactId = $row['contact_id'];
            unset($row['contact_id']);
            $grouped[$contactId][] = $row;
        }

        return $grouped;
    }

    public function findByTag(string $tagId, int $limit = 50, int $offset = 0): array
    {
        $t = $this->db->table(Connection::TABLE_CONTACTS);
        $pivotTable = $this->db->table(Connection::TABLE_CONTACT_TAG);

        $rows = $this->db->getResults(
            "SELECT c.* FROM {$t} c INNER JOIN {$pivotTable} ct ON c.id = ct.contact_id WHERE ct.tag_id = %s ORDER BY c.created_at DESC LIMIT %d OFFSET %d",
            $tagId,
            $limit,
            $offset,
        );

        return array_map([self::class, 'decodeRow'], $rows);
    }

    public function countByTag(string $tagId): int
    {
        $pivotTable = $this->db->table(Connection::TABLE_CONTACT_TAG);

        return (int) $this->db->getVar(
            "SELECT COUNT(*) FROM {$pivotTable} WHERE tag_id = %s",
            $tagId,
        );
    }

    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $t = $this->db->table(Connection::TABLE_CONTACTS);
        $ph = self::placeholders($ids);

        $rows = $this->db->getResults("SELECT * FROM {$t} WHERE id IN ({$ph})", ...$ids);

        return array_map([self::class, 'decodeRow'], $rows);
    }

    public function bulkDelete(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $t = $this->db->table(Connection::TABLE_CONTACTS);
        $pivotTable = $this->db->table(Connection::TABLE_CONTACT_TAG);
        $affected = 0;

        foreach (array_chunk($ids, self::BULK_CHUNK_SIZE) as $chunk) {
            $ph = self::placeholders($chunk);
            $this->db->query("DELETE FROM {$pivotTable} WHERE contact_id IN ({$ph})", ...$chunk);
            $affected += $this->db->query("DELETE FROM {$t} WHERE id IN ({$ph})", ...$chunk);
        }

        return $affected;
    }

    public function bulkUpdateStatus(array $ids, string $status): int
    {
        if (empty($ids)) {
            return 0;
        }

        $t = $this->db->table(Connection::TABLE_CONTACTS);
        $ph = self::placeholders($ids);
        $now = current_time('mysql');

        return $this->db->query(
            "UPDATE {$t} SET status = %s, updated_at = %s WHERE id IN ({$ph})",
            $status,
            $now,
            ...$ids,
        );
    }

    public function bulkAddTag(array $contactIds, string $tagId): int
    {
        if (empty($tagId) || empty($contactIds)) {
            return 0;
        }

        $t = $this->db->table(Connection::TABLE_CONTACT_TAG);
        $now = current_time('mysql');
        $affected = 0;

        foreach (array_chunk($contactIds, self::BULK_CHUNK_SIZE) as $chunk) {
            $values = [];
            $params = [];

            foreach ($chunk as $contactId) {
                $values[] = '(%s, %s, %s)';
                $params[] = $contactId;
                $params[] = $tagId;
                $params[] = $now;
            }

            $sql = "INSERT IGNORE INTO {$t} (contact_id, tag_id, created_at) VALUES " . implode(', ', $values);
            $affected += $this->db->query($sql, ...$params);
        }

        return $affected;
    }

    public function bulkRemoveTag(array $contactIds, string $tagId): int
    {
        if (empty($tagId) || empty($contactIds)) {
            return 0;
        }

        $t = $this->db->table(Connection::TABLE_CONTACT_TAG);
        $affected = 0;

        foreach (array_chunk($contactIds, self::BULK_CHUNK_SIZE) as $chunk) {
            $ph = self::placeholders($chunk);
            $affected += $this->db->query(
                "DELETE FROM {$t} WHERE tag_id = %s AND contact_id IN ({$ph})",
                $tagId,
                ...$chunk,
            );
        }

        return $affected;
    }

    public function eachSubscribedWithEmail(callable $callback, int $batchSize = 500): void
    {
        $table = $this->db->table(Connection::TABLE_CONTACTS);
        $lastId = '';

        do {
            $batch = $this->db->getResults(
                "SELECT * FROM {$table}
                 WHERE email IS NOT NULL AND email != '' AND status = 'subscribed' AND id > %s
                 ORDER BY id ASC LIMIT %d",
                $lastId,
                $batchSize,
            );

            if (!empty($batch)) {
                $batch = array_map([self::class, 'decodeRow'], $batch);
                $callback($batch);
                $lastId = end($batch)['id'];
            }
        } while (count($batch) === $batchSize);
    }

    public function setChannelOptOut(string $contactId, string $channel): void
    {
        $optOuts = $this->loadOptOuts($contactId);
        if ($optOuts === null) {
            return;
        }

        $optOuts[$channel] = current_time('mysql');
        $this->saveOptOuts($contactId, $optOuts);
    }

    public function clearChannelOptOut(string $contactId, string $channel): void
    {
        $optOuts = $this->loadOptOuts($contactId);
        if ($optOuts === null) {
            return;
        }

        unset($optOuts[$channel]);
        $this->saveOptOuts($contactId, $optOuts);
    }

    public function isChannelOptedOut(string $contactId, string $channel): bool
    {
        $optOuts = $this->loadOptOuts($contactId);
        return $optOuts !== null && !empty($optOuts[$channel]);
    }

    public function getChannelOptOuts(string $contactId): array
    {
        return $this->loadOptOuts($contactId) ?? [];
    }

    private function loadOptOuts(string $contactId): ?array
    {
        $t = $this->db->table(Connection::TABLE_CONTACTS);
        $row = $this->db->getRow("SELECT channel_opt_outs FROM {$t} WHERE id = %s", $contactId);
        if ($row === null) {
            return null;
        }
        return json_decode($row['channel_opt_outs'] ?? '{}', true) ?: [];
    }

    private function saveOptOuts(string $contactId, array $optOuts): void
    {
        $now = current_time('mysql');
        $this->db->update(Connection::TABLE_CONTACTS, [
            'channel_opt_outs' => !empty($optOuts) ? wp_json_encode($optOuts) : null,
            'updated_at'       => $now,
        ], ['id' => $contactId]);

        do_action('wsms_contact_updated', $contactId, ['channel_opt_outs' => $optOuts]);
    }

    public static function normalizePhone(string $phone): string
    {
        return preg_replace('/[\s\-\(\)\.]+/', '', $phone);
    }

    private static function placeholders(array $items): string
    {
        return implode(',', array_fill(0, count($items), '%s'));
    }

    private function buildWhereClause(array $filters): array
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
            $like = '%' . $this->db->escLike($filters['search']) . '%';
            $params = array_merge($params, [$like, $like, $like, $like]);
        }

        return [$where, $params];
    }

    private static function decodeRow(array $row): array
    {
        if (isset($row['custom_fields']) && is_string($row['custom_fields'])) {
            $row['custom_fields'] = json_decode($row['custom_fields'], true) ?? [];
        }
        if (isset($row['channel_opt_outs']) && is_string($row['channel_opt_outs'])) {
            $row['channel_opt_outs'] = json_decode($row['channel_opt_outs'], true) ?? [];
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
