<?php

namespace WSms\Flow\Storage;

use WSms\Database\Connection;
use WSms\Exception\NotFoundException;
use WSms\Flow\Contracts\Flow;
use WSms\Flow\Contracts\FlowRepositoryInterface;

defined('ABSPATH') || exit;

class FlowRepository implements FlowRepositoryInterface
{
    public function __construct(private readonly Connection $db) {}

    public function save(Flow $flow): string
    {
        $table = $this->db->table(Connection::TABLE_FLOWS);

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

        $existing = $this->db->getVar("SELECT id FROM {$table} WHERE id = %s", $flow->getId());

        if ($existing) {
            unset($data['id']);
            $data['updated_at'] = current_time('mysql');
            $this->db->update(Connection::TABLE_FLOWS, $data, ['id' => $flow->getId()]);
        } else {
            $data['created_at'] = current_time('mysql');
            $data['updated_at'] = current_time('mysql');
            $this->db->insert(Connection::TABLE_FLOWS, $data);
        }

        return $flow->getId();
    }

    public function find(string $id): ?Flow
    {
        $table = $this->db->table(Connection::TABLE_FLOWS);

        $row = $this->db->getRow("SELECT * FROM {$table} WHERE id = %s", $id);

        return $row ? Flow::fromArray($row) : null;
    }

    public function findOrFail(string $id): Flow
    {
        $flow = $this->find($id);

        if ($flow === null) {
            throw NotFoundException::entity('Flow', $id);
        }

        return $flow;
    }

    public function findByTrigger(string $triggerType): array
    {
        $table = $this->db->table(Connection::TABLE_FLOWS);

        $rows = $this->db->getResults(
            "SELECT * FROM {$table} WHERE trigger_type = %s AND status = 'active' ORDER BY priority DESC",
            $triggerType,
        );

        return array_map(fn($row) => Flow::fromArray($row), $rows);
    }

    public function findAll(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $table = $this->db->table(Connection::TABLE_FLOWS);

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

        $rows = $this->db->getResults($sql, ...$params);

        return array_map(fn($row) => Flow::fromArray($row), $rows);
    }

    public function delete(string $id): bool
    {
        $result = (bool) $this->db->delete(Connection::TABLE_FLOWS, ['id' => $id]);

        if ($result) {
            do_action('wsms_flow_deleted', $id);
        }

        return $result;
    }

    public function updateStatus(string $id, string $status): bool
    {
        $result = (bool) $this->db->update(
            Connection::TABLE_FLOWS,
            ['status' => $status, 'updated_at' => current_time('mysql')],
            ['id' => $id],
        );

        if ($result && $status !== 'active') {
            do_action('wsms_flow_deactivated', $id);
        }

        return $result;
    }

    public function publish(string $id): bool
    {
        $table = $this->db->table(Connection::TABLE_FLOWS);

        $row = $this->db->getRow("SELECT steps, trigger_type, trigger_config FROM {$table} WHERE id = %s", $id);
        if (!$row || !$row['steps']) {
            return false;
        }

        $result = (bool) $this->db->update(Connection::TABLE_FLOWS, [
            'published_steps' => $row['steps'],
            'published_at'    => current_time('mysql'),
            'status'          => 'active',
            'updated_at'      => current_time('mysql'),
        ], ['id' => $id]);

        if ($result) {
            $triggerConfig = json_decode($row['trigger_config'] ?? '{}', true) ?: [];
            do_action('wsms_flow_published', $id, $row['trigger_type'], $triggerConfig);
        }

        return $result;
    }
}
