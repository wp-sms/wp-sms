<?php

namespace WSms\Contact;

use WSms\Dependencies\Symfony\Component\Uid\Ulid;
use WSms\Contact\Contracts\TagRepositoryInterface;

defined('ABSPATH') || exit;

class TagRepository implements TagRepositoryInterface
{
    public function create(array $data): string
    {
        global $wpdb;

        $id = (string) new Ulid();
        $slug = $this->resolveSlug($data['name'], $data['slug'] ?? null, null, $id);

        $wpdb->insert($wpdb->prefix . 'wsms_tags', [
            'id'    => $id,
            'name'  => $data['name'],
            'slug'  => $slug,
            'color' => $data['color'] ?? '#3b82f6',
        ]);

        return $id;
    }

    public function update(string $id, array $data): bool
    {
        global $wpdb;

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

        return (bool) $wpdb->update($wpdb->prefix . 'wsms_tags', $update, ['id' => $id]);
    }

    public function find(string $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}wsms_tags WHERE id = %s", $id),
            ARRAY_A,
        );
        return $row ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}wsms_tags WHERE slug = %s", $slug),
            ARRAY_A,
        );
        return $row ?: null;
    }

    public function findAll(): array
    {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}wsms_tags ORDER BY name ASC",
            ARRAY_A,
        ) ?: [];
    }

    public function delete(string $id): bool
    {
        global $wpdb;
        // Cascade: remove from junction table first
        $wpdb->delete($wpdb->prefix . 'wsms_contact_tag', ['tag_id' => $id]);
        return (bool) $wpdb->delete($wpdb->prefix . 'wsms_tags', ['id' => $id]);
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
        global $wpdb;
        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}wsms_contact_tag WHERE tag_id = %s", $id),
        );
    }
}
