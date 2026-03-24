<?php

namespace WSms\Contact;

use WSms\Database\Connection;
use WSms\Dependencies\Symfony\Component\Uid\Ulid;
use WSms\Contact\Contracts\ListRepositoryInterface;
use WSms\Exception\NotFoundException;

defined('ABSPATH') || exit;

class ListRepository implements ListRepositoryInterface
{
    public function __construct(private readonly Connection $db) {}

    public function create(array $data): string
    {
        $id = (string) new Ulid();
        $now = current_time('mysql');

        $this->db->insert(Connection::TABLE_LISTS, [
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
        $update = ['updated_at' => current_time('mysql')];

        foreach (['name', 'type', 'tag_id', 'description'] as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }

        if (array_key_exists('conditions', $data)) {
            $update['conditions'] = $data['conditions'] !== null ? wp_json_encode($data['conditions']) : null;
        }

        return (bool) $this->db->update(Connection::TABLE_LISTS, $update, ['id' => $id]);
    }

    public function find(string $id): ?array
    {
        $table = $this->db->table(Connection::TABLE_LISTS);
        $row = $this->db->getRow("SELECT * FROM {$table} WHERE id = %s", $id);
        return $row ? self::decodeRow($row) : null;
    }

    public function findOrFail(string $id): array
    {
        $row = $this->find($id);
        if ($row === null) {
            throw NotFoundException::entity('List', $id);
        }
        return $row;
    }

    public function findAll(?string $type = null): array
    {
        $table = $this->db->table(Connection::TABLE_LISTS);

        if ($type !== null) {
            $rows = $this->db->getResults("SELECT * FROM {$table} WHERE type = %s ORDER BY created_at DESC", $type);
        } else {
            $rows = $this->db->getResults("SELECT * FROM {$table} ORDER BY created_at DESC");
        }

        return array_map([self::class, 'decodeRow'], $rows);
    }

    public function delete(string $id): bool
    {
        return (bool) $this->db->delete(Connection::TABLE_LISTS, ['id' => $id]);
    }

    public function updateContactCount(string $id, int $count): bool
    {
        return (bool) $this->db->update(
            Connection::TABLE_LISTS,
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
