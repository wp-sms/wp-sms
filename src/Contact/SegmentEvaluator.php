<?php

namespace WSms\Contact;

use WSms\Contact\Contracts\SegmentEvaluatorInterface;

defined('ABSPATH') || exit;

class SegmentEvaluator implements SegmentEvaluatorInterface
{
    public function evaluate(array $conditions, int $limit = 1000, int $offset = 0): array
    {
        global $wpdb;

        $query = $this->buildQuery($conditions, $wpdb);
        $query .= $wpdb->prepare(' LIMIT %d OFFSET %d', $limit, $offset);

        return $wpdb->get_results($query, ARRAY_A) ?: [];
    }

    public function count(array $conditions): int
    {
        global $wpdb;

        $query = $this->buildCountQuery($conditions, $wpdb);

        return (int) $wpdb->get_var($query);
    }

    private function buildQuery(array $conditions, object $wpdb): string
    {
        $table = $wpdb->prefix . 'wsms_contacts';
        $where = $this->buildWhere($conditions, $wpdb);

        return "SELECT c.* FROM {$table} c WHERE {$where} ORDER BY c.created_at DESC";
    }

    private function buildCountQuery(array $conditions, object $wpdb): string
    {
        $table = $wpdb->prefix . 'wsms_contacts';
        $where = $this->buildWhere($conditions, $wpdb);

        return "SELECT COUNT(*) FROM {$table} c WHERE {$where}";
    }

    private function buildWhere(array $conditions, object $wpdb): string
    {
        $match = $conditions['match'] ?? 'all';
        $joiner = $match === 'all' ? ' AND ' : ' OR ';

        $clauses = [];

        foreach ($conditions['conditions'] ?? [] as $condition) {
            $clause = $this->buildConditionClause($condition, $wpdb);
            if ($clause) {
                $clauses[] = $clause;
            }
        }

        foreach ($conditions['groups'] ?? [] as $group) {
            $subWhere = $this->buildWhere($group, $wpdb);
            if ($subWhere) {
                $clauses[] = "({$subWhere})";
            }
        }

        return $clauses ? implode($joiner, $clauses) : '1=1';
    }

    private function buildConditionClause(array $condition, object $wpdb): ?string
    {
        $type = $condition['type'] ?? '';

        return match ($type) {
            'attribute' => $this->buildAttributeClause($condition, $wpdb),
            'tag'       => $this->buildTagClause($condition, $wpdb),
            default     => null,
        };
    }

    private function buildAttributeClause(array $condition, object $wpdb): ?string
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
            $allowed = ['email', 'phone', 'first_name', 'last_name', 'status', 'source'];
            if (!in_array($field, $allowed)) {
                return null;
            }
            $column = "c.{$field}";
        }

        return match ($operator) {
            'equals'       => $wpdb->prepare("{$column} = %s", $value),
            'not_equals'   => $wpdb->prepare("{$column} != %s", $value),
            'contains'     => $wpdb->prepare("{$column} LIKE %s", '%' . $wpdb->esc_like($value) . '%'),
            'starts_with'  => $wpdb->prepare("{$column} LIKE %s", $wpdb->esc_like($value) . '%'),
            'is_empty'     => "({$column} IS NULL OR {$column} = '')",
            'is_not_empty' => "({$column} IS NOT NULL AND {$column} != '')",
            default        => null,
        };
    }

    private function buildTagClause(array $condition, object $wpdb): ?string
    {
        $operator = $condition['operator'] ?? 'has';
        $tagSlug = $condition['value'] ?? '';
        $tagsTable = $wpdb->prefix . 'wsms_tags';
        $pivotTable = $wpdb->prefix . 'wsms_contact_tag';

        $subquery = $wpdb->prepare(
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
