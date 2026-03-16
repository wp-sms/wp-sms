<?php

namespace WSms\Flow\Storage;

use WSms\Dependencies\Symfony\Component\Uid\Ulid;
use WSms\Flow\Contracts\Flow;
use WSms\Flow\Contracts\FlowRepositoryInterface;

defined('ABSPATH') || exit;

class FlowRepository implements FlowRepositoryInterface
{
    public function save(Flow $flow): string
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wsms_flows';

        $data = [
            'id'             => $flow->getId(),
            'name'           => $flow->getName(),
            'description'    => $flow->getDescription(),
            'trigger_type'   => $flow->getTriggerType(),
            'trigger_config' => wp_json_encode($flow->getTriggerConfig()),
            'steps'          => wp_json_encode($flow->getSteps()),
            'status'         => $flow->getStatus(),
            'priority'       => $flow->getPriority(),
            'created_by'     => $flow->getCreatedBy(),
        ];

        if ($flow->getPublishedSteps() !== null) {
            $data['published_steps'] = wp_json_encode($flow->getPublishedSteps());
            $data['published_at'] = $flow->getPublishedAt()?->format('Y-m-d H:i:s');
        }

        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE id = %s", $flow->getId()));

        if ($existing) {
            unset($data['id']);
            $data['updated_at'] = current_time('mysql');
            $wpdb->update($table, $data, ['id' => $flow->getId()]);
        } else {
            $data['created_at'] = current_time('mysql');
            $data['updated_at'] = current_time('mysql');
            $wpdb->insert($table, $data);
        }

        return $flow->getId();
    }

    public function find(string $id): ?Flow
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wsms_flows';

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %s", $id), ARRAY_A);

        return $row ? Flow::fromArray($row) : null;
    }

    public function findByTrigger(string $triggerType): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wsms_flows';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE trigger_type = %s AND status = 'active' ORDER BY priority DESC",
                $triggerType,
            ),
            ARRAY_A,
        );

        return array_map(fn($row) => Flow::fromArray($row), $rows ?: []);
    }

    public function findAll(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wsms_flows';

        $where = '1=1';
        $params = [];

        if (!empty($filters['status'])) {
            $where .= ' AND status = %s';
            $params[] = $filters['status'];
        }
        if (!empty($filters['trigger_type'])) {
            $where .= ' AND trigger_type = %s';
            $params[] = $filters['trigger_type'];
        }

        $sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY updated_at DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        return array_map(fn($row) => Flow::fromArray($row), $rows ?: []);
    }

    public function delete(string $id): bool
    {
        global $wpdb;
        return (bool) $wpdb->delete($wpdb->prefix . 'wsms_flows', ['id' => $id]);
    }

    public function updateStatus(string $id, string $status): bool
    {
        global $wpdb;
        return (bool) $wpdb->update(
            $wpdb->prefix . 'wsms_flows',
            ['status' => $status, 'updated_at' => current_time('mysql')],
            ['id' => $id],
        );
    }

    public function publish(string $id): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wsms_flows';

        $steps = $wpdb->get_var($wpdb->prepare("SELECT steps FROM {$table} WHERE id = %s", $id));
        if (!$steps) {
            return false;
        }

        return (bool) $wpdb->update($table, [
            'published_steps' => $steps,
            'published_at'    => current_time('mysql'),
            'status'          => 'active',
            'updated_at'      => current_time('mysql'),
        ], ['id' => $id]);
    }
}
