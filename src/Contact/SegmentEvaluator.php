<?php

namespace WSms\Contact;

use WSms\Contact\Contracts\SegmentEvaluatorInterface;
use WSms\Database\Connection;

defined('ABSPATH') || exit;

class SegmentEvaluator implements SegmentEvaluatorInterface
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function evaluate(array $conditions, int $limit = 1000, int $offset = 0): array
    {
        $query = $this->buildQuery($conditions);
        $query .= $this->db->prepare(' LIMIT %d OFFSET %d', $limit, $offset);

        return $this->db->getResults($query);
    }

    public function count(array $conditions): int
    {
        $query = $this->buildCountQuery($conditions);

        return (int) $this->db->getVar($query);
    }

    private function buildQuery(array $conditions): string
    {
        $table = $this->db->table(Connection::TABLE_CONTACTS);
        $where = $this->buildWhere($conditions);

        return "SELECT c.* FROM {$table} c WHERE {$where} ORDER BY c.created_at DESC";
    }

    private function buildCountQuery(array $conditions): string
    {
        $table = $this->db->table(Connection::TABLE_CONTACTS);
        $where = $this->buildWhere($conditions);

        return "SELECT COUNT(*) FROM {$table} c WHERE {$where}";
    }

    private function buildWhere(array $conditions): string
    {
        $match = $conditions['match'] ?? 'all';
        $joiner = $match === 'all' ? ' AND ' : ' OR ';

        $clauses = [];

        foreach ($conditions['conditions'] ?? [] as $condition) {
            $clause = $this->buildConditionClause($condition);
            if ($clause) {
                $clauses[] = $clause;
            }
        }

        foreach ($conditions['groups'] ?? [] as $group) {
            $subWhere = $this->buildWhere($group);
            if ($subWhere) {
                $clauses[] = "({$subWhere})";
            }
        }

        return $clauses ? implode($joiner, $clauses) : '1=1';
    }

    private function buildConditionClause(array $condition): ?string
    {
        $type = $condition['type'] ?? '';

        return match ($type) {
            'attribute' => $this->buildAttributeClause($condition),
            'tag'       => $this->buildTagClause($condition),
            default     => null,
        };
    }

    private function buildAttributeClause(array $condition): ?string
    {
        $field = $condition['field'] ?? '';
        $operator = $condition['operator'] ?? 'equals';
        $value = $condition['value'] ?? '';

        // Custom fields use JSON extraction — validate key to prevent SQL injection
        if (str_starts_with($field, 'custom.')) {
            $jsonKey = substr($field, 7);
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $jsonKey)) {
                return null;
            }
            $column = "JSON_UNQUOTE(JSON_EXTRACT(c.custom_fields, '$.{$jsonKey}'))";
        } else {
            $allowed = ['email', 'phone', 'first_name', 'last_name', 'status', 'source', 'email_verified', 'phone_verified'];
            if (!in_array($field, $allowed)) {
                return null;
            }
            $column = "c.{$field}";
        }

        return match ($operator) {
            'equals'       => $this->db->prepare("{$column} = %s", $value),
            'not_equals'   => $this->db->prepare("{$column} != %s", $value),
            'contains'     => $this->db->prepare("{$column} LIKE %s", '%' . $this->db->escLike($value) . '%'),
            'starts_with'  => $this->db->prepare("{$column} LIKE %s", $this->db->escLike($value) . '%'),
            'is_empty'     => "({$column} IS NULL OR {$column} = '')",
            'is_not_empty' => "({$column} IS NOT NULL AND {$column} != '')",
            'is_true'      => "{$column} = 1",
            'is_false'     => "{$column} = 0",
            default        => null,
        };
    }

    private function buildTagClause(array $condition): ?string
    {
        $operator = $condition['operator'] ?? 'has';
        $tagSlug = $condition['value'] ?? '';
        $tagsTable = $this->db->table(Connection::TABLE_TAGS);
        $pivotTable = $this->db->table(Connection::TABLE_CONTACT_TAG);

        $subquery = $this->db->prepare(
            "SELECT 1 FROM {$pivotTable} ct_sub INNER JOIN {$tagsTable} t_sub ON ct_sub.tag_id = t_sub.id WHERE ct_sub.contact_id = c.id AND t_sub.slug = %s",
            $tagSlug,
        );

        return match ($operator) {
            'has'      => "EXISTS ({$subquery})",
            'not_has'  => "NOT EXISTS ({$subquery})",
            default    => null,
        };
    }
}
