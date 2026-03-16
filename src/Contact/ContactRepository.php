<?php

namespace WSms\Contact;

use WSms\Dependencies\Symfony\Component\Uid\Ulid;
use WSms\Contact\Contracts\ContactRepositoryInterface;

defined('ABSPATH') || exit;

class ContactRepository implements ContactRepositoryInterface
{
    public function create(array $data): string
    {
        global $wpdb;

        $id = (string) new Ulid();
        $now = current_time('mysql');

        $wpdb->insert($wpdb->prefix . 'wsms_contacts', [
            'id'            => $id,
            'email'         => $data['email'] ?? null,
            'phone'         => $data['phone'] ?? null,
            'first_name'    => $data['first_name'] ?? null,
            'last_name'     => $data['last_name'] ?? null,
            'wp_user_id'    => $data['wp_user_id'] ?? null,
            'status'        => $data['status'] ?? 'subscribed',
            'custom_fields' => isset($data['custom_fields']) ? wp_json_encode($data['custom_fields']) : null,
            'source'        => $data['source'] ?? 'manual',
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        return $id;
    }

    public function update(string $id, array $data): bool
    {
        global $wpdb;

        $update = ['updated_at' => current_time('mysql')];

        foreach (['email', 'phone', 'first_name', 'last_name', 'wp_user_id', 'status', 'source'] as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }

        if (isset($data['custom_fields'])) {
            $update['custom_fields'] = wp_json_encode($data['custom_fields']);
        }

        return (bool) $wpdb->update($wpdb->prefix . 'wsms_contacts', $update, ['id' => $id]);
    }

    public function find(string $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}wsms_contacts WHERE id = %s", $id),
            ARRAY_A,
        );
        return $row ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}wsms_contacts WHERE email = %s", $email),
            ARRAY_A,
        );
        return $row ?: null;
    }

    public function findByPhone(string $phone): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}wsms_contacts WHERE phone = %s", $phone),
            ARRAY_A,
        );
        return $row ?: null;
    }

    public function findByWpUser(int $userId): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}wsms_contacts WHERE wp_user_id = %d", $userId),
            ARRAY_A,
        );
        return $row ?: null;
    }

    public function findAll(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wsms_contacts';

        $where = '1=1';
        $params = [];

        if (!empty($filters['status'])) {
            $where .= ' AND status = %s';
            $params[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where .= ' AND (email LIKE %s OR phone LIKE %s OR first_name LIKE %s OR last_name LIKE %s)';
            $like = '%' . $wpdb->esc_like($filters['search']) . '%';
            $params = array_merge($params, [$like, $like, $like, $like]);
        }

        $params[] = $limit;
        $params[] = $offset;

        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d", ...$params),
            ARRAY_A,
        ) ?: [];
    }

    public function count(array $filters = []): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wsms_contacts';

        $where = '1=1';
        $params = [];

        if (!empty($filters['status'])) {
            $where .= ' AND status = %s';
            $params[] = $filters['status'];
        }

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
        $wpdb->replace($wpdb->prefix . 'wsms_contact_tag', [
            'contact_id' => $contactId,
            'tag_id'     => $tagId,
            'created_at' => current_time('mysql'),
        ]);
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
}
