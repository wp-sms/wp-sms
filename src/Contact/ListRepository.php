<?php

namespace WSms\Contact;

use WSms\Dependencies\Symfony\Component\Uid\Ulid;
use WSms\Contact\Contracts\ListRepositoryInterface;

defined('ABSPATH') || exit;

class ListRepository implements ListRepositoryInterface
{
    public function create(array $data): string
    {
        global $wpdb;

        $id = (string) new Ulid();
        $now = current_time('mysql');

        $wpdb->insert($wpdb->prefix . 'wsms_lists', [
            'id'            => $id,
            'name'          => $data['name'],
            'type'          => $data['type'] ?? 'dynamic',
            'conditions'    => isset($data['conditions']) ? wp_json_encode($data['conditions']) : null,
            'tag_id'        => $data['tag_id'] ?? null,
            'description'   => $data['description'] ?? null,
            'contact_count' => 0,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        return $id;
    }

    public function update(string $id, array $data): bool
    {
        global $wpdb;

        $update = ['updated_at' => current_time('mysql')];

        foreach (['name', 'type', 'tag_id', 'description'] as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }

        if (array_key_exists('conditions', $data)) {
            $update['conditions'] = $data['conditions'] !== null ? wp_json_encode($data['conditions']) : null;
        }

        return (bool) $wpdb->update($wpdb->prefix . 'wsms_lists', $update, ['id' => $id]);
    }

    public function find(string $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}wsms_lists WHERE id = %s", $id),
            ARRAY_A,
        );
        return $row ? self::decodeRow($row) : null;
    }

    public function findAll(?string $type = null): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wsms_lists';

        if ($type !== null) {
            $rows = $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM {$table} WHERE type = %s ORDER BY created_at DESC", $type),
                ARRAY_A,
            ) ?: [];
        } else {
            $rows = $wpdb->get_results(
                "SELECT * FROM {$table} ORDER BY created_at DESC",
                ARRAY_A,
            ) ?: [];
        }

        return array_map([self::class, 'decodeRow'], $rows);
    }

    public function delete(string $id): bool
    {
        global $wpdb;
        return (bool) $wpdb->delete($wpdb->prefix . 'wsms_lists', ['id' => $id]);
    }

    public function updateContactCount(string $id, int $count): bool
    {
        global $wpdb;
        return (bool) $wpdb->update(
            $wpdb->prefix . 'wsms_lists',
            ['contact_count' => $count, 'updated_at' => current_time('mysql')],
            ['id' => $id],
        );
    }

    private static function decodeRow(array $row): array
    {
        if (isset($row['conditions']) && is_string($row['conditions'])) {
            $row['conditions'] = json_decode($row['conditions'], true);
        }
        return $row;
    }
}
