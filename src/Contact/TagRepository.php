<?php

namespace WSms\Contact;

use WSms\Contact\Contracts\TagRepositoryInterface;
use WSms\Database\Connection;
use WSms\Dependencies\Symfony\Component\Uid\Ulid;
use WSms\Exception\NotFoundException;

defined('ABSPATH') || exit;

class TagRepository implements TagRepositoryInterface
{
    public function __construct(private readonly Connection $db) {}

    public function create(array $data): string
    {
        $id = (string) new Ulid();
        $slug = $this->resolveSlug($data['name'], $data['slug'] ?? null, null, $id);

        $this->db->insert(Connection::TABLE_TAGS, [
            'id'    => $id,
            'name'  => $data['name'],
            'slug'  => $slug,
            'color' => $data['color'] ?? '#3b82f6',
        ]);

        return $id;
    }

    public function update(string $id, array $data): bool
    {
        $update = [];

        if (isset($data['name'])) {
            $update['name'] = $data['name'];
            $update['slug'] = $this->resolveSlug($data['name'], $data['slug'] ?? null, $id, $id);
        }

        if (isset($data['color'])) {
            $update['color'] = $data['color'];
        }

        if (empty($update)) {
            return false;
        }

        return (bool) $this->db->update(Connection::TABLE_TAGS, $update, ['id' => $id]);
    }

    public function find(string $id): ?array
    {
        $table = $this->db->table(Connection::TABLE_TAGS);

        return $this->db->getRow("SELECT * FROM {$table} WHERE id = %s", $id);
    }

    public function findOrFail(string $id): array
    {
        $row = $this->find($id);

        if ($row === null) {
            throw NotFoundException::entity('Tag', $id);
        }

        return $row;
    }

    public function findBySlug(string $slug): ?array
    {
        $table = $this->db->table(Connection::TABLE_TAGS);

        return $this->db->getRow("SELECT * FROM {$table} WHERE slug = %s", $slug);
    }

    public function findAll(): array
    {
        $table = $this->db->table(Connection::TABLE_TAGS);

        return $this->db->getResults("SELECT * FROM {$table} ORDER BY name ASC");
    }

    public function delete(string $id): bool
    {
        // Cascade: remove from junction table first
        $this->db->delete(Connection::TABLE_CONTACT_TAG, ['tag_id' => $id]);

        return (bool) $this->db->delete(Connection::TABLE_TAGS, ['id' => $id]);
    }

    private function resolveSlug(string $name, ?string $explicitSlug, ?string $excludeId, string $suffixSource): string
    {
        $slug = $explicitSlug ? sanitize_title($explicitSlug) : sanitize_title($name);
        if ($slug === '') {
            $slug = strtolower(rawurlencode($name));
        }

        $existing = $this->findBySlug($slug);
        if ($existing && ($excludeId === null || $existing['id'] !== $excludeId)) {
            $slug .= '-' . substr($suffixSource, -4);
        }

        return $slug;
    }

    public function getContactCount(string $id): int
    {
        $table = $this->db->table(Connection::TABLE_CONTACT_TAG);

        return (int) $this->db->getVar("SELECT COUNT(*) FROM {$table} WHERE tag_id = %s", $id);
    }

    public function getContactCounts(): array
    {
        $t = $this->db->table(Connection::TABLE_CONTACT_TAG);
        $rows = $this->db->getResults("SELECT tag_id, COUNT(*) AS count FROM {$t} GROUP BY tag_id");
        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['tag_id']] = (int) $row['count'];
        }
        return $counts;
    }
}
