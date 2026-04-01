<?php

namespace WSms\Campaign;

use WSms\Contact\Contracts\SegmentEvaluatorInterface;
use WSms\Database\Connection;

defined('ABSPATH') || exit;

class AudienceResolver
{
    public function __construct(
        private readonly SegmentEvaluatorInterface $segmentEvaluator,
        private readonly Connection $db,
    ) {
    }

    /**
     * Resolve audience to a paginated batch of recipients using cursor-based pagination.
     */
    public function resolve(array $audience, string $channel, int $batchSize = 500, ?string $afterId = null): AudienceBatch
    {
        $channelField = $this->getChannelField($channel);
        $excludeUnsubscribed = $audience['exclude_unsubscribed'] ?? true;

        $unionParts = [];
        $manualRecipients = [];

        foreach ($audience['sources'] ?? [] as $source) {
            $sql = match ($source['type'] ?? '') {
                'segment'  => $this->buildSegmentQuery($source, $channelField, $excludeUnsubscribed),
                'tags'     => $this->buildTagsQuery($source, $channelField, $excludeUnsubscribed),
                'wp_roles' => $this->buildRolesQuery($source, $channelField),
                'manual'   => null, // handled separately
                default    => null,
            };

            if ($sql) {
                $unionParts[] = $sql;
            }

            if (($source['type'] ?? '') === 'manual') {
                $manualRecipients = array_merge($manualRecipients, $source['recipients'] ?? []);
            }
        }

        // Get contact-based recipients via UNION + cursor pagination
        $contactRecipients = [];
        $lastContactId = null;
        $hasMore = false;

        if (!empty($unionParts)) {
            $unionSql = implode(' UNION ', $unionParts);

            $cursorClause = '';
            if ($afterId) {
                $cursorClause = $this->db->prepare(' AND id > %s', $afterId);
            }

            $query = "SELECT * FROM ({$unionSql}) AS audience WHERE 1=1 {$cursorClause} ORDER BY id ASC LIMIT %d";
            $rows = $this->db->getResults($query, $batchSize + 1);

            if (count($rows) > $batchSize) {
                $hasMore = true;
                array_pop($rows);
            }

            foreach ($rows as $row) {
                $contactRecipients[] = [
                    'contact_id'    => $row['id'],
                    'recipient'     => $row[$channelField] ?? $row['phone'] ?? $row['email'] ?? '',
                    'first_name'    => $row['first_name'] ?? null,
                    'last_name'     => $row['last_name'] ?? null,
                    'custom_fields' => isset($row['custom_fields']) ? (is_string($row['custom_fields']) ? json_decode($row['custom_fields'], true) : $row['custom_fields']) : [],
                ];
                $lastContactId = $row['id'];
            }
        }

        // Add manual recipients (only in first batch, i.e. no cursor)
        if (!$afterId && !empty($manualRecipients)) {
            $seen = array_flip(array_column($contactRecipients, 'recipient'));
            foreach (array_unique($manualRecipients) as $recipient) {
                $recipient = trim($recipient);
                if ($recipient !== '' && !isset($seen[$recipient])) {
                    $contactRecipients[] = [
                        'contact_id'    => null,
                        'recipient'     => $recipient,
                        'first_name'    => null,
                        'last_name'     => null,
                        'custom_fields' => [],
                    ];
                    $seen[$recipient] = true;
                }
            }
        }

        return new AudienceBatch(
            recipients: $contactRecipients,
            hasMore: $hasMore,
            totalCount: 0, // Total is tracked on the campaign row; avoid redundant count query per batch
            lastId: $lastContactId,
        );
    }

    /**
     * Count total audience size for a given audience config and channel.
     */
    public function count(array $audience, string $channel): int
    {
        $channelField = $this->getChannelField($channel);
        $excludeUnsubscribed = $audience['exclude_unsubscribed'] ?? true;

        $unionParts = [];
        $manualCount = 0;

        foreach ($audience['sources'] ?? [] as $source) {
            $sql = match ($source['type'] ?? '') {
                'segment'  => $this->buildSegmentQuery($source, $channelField, $excludeUnsubscribed),
                'tags'     => $this->buildTagsQuery($source, $channelField, $excludeUnsubscribed),
                'wp_roles' => $this->buildRolesQuery($source, $channelField),
                'manual'   => null,
                default    => null,
            };

            if ($sql) {
                $unionParts[] = $sql;
            }

            if (($source['type'] ?? '') === 'manual') {
                $manualCount += count(array_unique($source['recipients'] ?? []));
            }
        }

        $contactCount = 0;
        if (!empty($unionParts)) {
            $unionSql = implode(' UNION ', $unionParts);
            $contactCount = (int) $this->db->getVar("SELECT COUNT(*) FROM ({$unionSql}) AS audience");
        }

        return $contactCount + $manualCount;
    }

    /**
     * Count contacts that will be skipped due to missing channel field.
     */
    public function countSkipped(array $audience, string $channel): int
    {
        $channelField = $this->getChannelField($channel);
        $excludeUnsubscribed = $audience['exclude_unsubscribed'] ?? true;

        $totalWithField = $this->count($audience, $channel);

        // Count without the channel field filter to get total audience
        $unionParts = [];
        foreach ($audience['sources'] ?? [] as $source) {
            $sql = match ($source['type'] ?? '') {
                'segment'  => $this->buildSegmentQuery($source, null, $excludeUnsubscribed),
                'tags'     => $this->buildTagsQuery($source, null, $excludeUnsubscribed),
                'wp_roles' => $this->buildRolesQuery($source, null),
                default    => null,
            };
            if ($sql) {
                $unionParts[] = $sql;
            }
        }

        $totalWithout = 0;
        if (!empty($unionParts)) {
            $unionSql = implode(' UNION ', $unionParts);
            $totalWithout = (int) $this->db->getVar("SELECT COUNT(*) FROM ({$unionSql}) AS audience");
        }

        return max(0, $totalWithout - $totalWithField);
    }

    private function getChannelField(string $channel): string
    {
        return match ($channel) {
            'sms', 'whatsapp' => 'phone',
            'email'           => 'email',
            default           => 'phone',
        };
    }

    private function buildChannelOptOutClause(?string $channelField): string
    {
        if ($channelField === null) {
            return '';
        }

        // Map DB column → channel key in JSON
        $channelKey = match ($channelField) {
            'email' => 'email',
            'phone' => 'sms',
            default => 'sms',
        };

        return " AND (c.channel_opt_outs IS NULL OR JSON_EXTRACT(c.channel_opt_outs, '$.{$channelKey}') IS NULL)";
    }

    private function buildSegmentQuery(array $source, ?string $channelField, bool $excludeUnsubscribed): string
    {
        $table = $this->db->table(Connection::TABLE_CONTACTS);
        $conditions = $source['conditions'] ?? [];

        $segmentWhere = '1=1';
        if (!empty($conditions)) {
            $segmentWhere = $this->buildSegmentWhere($conditions);
        }

        $channelClause = $channelField ? " AND c.{$channelField} IS NOT NULL AND c.{$channelField} != ''" : '';
        $statusClause = $excludeUnsubscribed
            ? " AND c.status NOT IN ('bounced', 'complained')" . $this->buildChannelOptOutClause($channelField)
            : '';

        return "SELECT DISTINCT c.id, c.phone, c.email, c.first_name, c.last_name, c.custom_fields FROM {$table} c WHERE {$segmentWhere}{$channelClause}{$statusClause}";
    }

    private function buildTagsQuery(array $source, ?string $channelField, bool $excludeUnsubscribed): ?string
    {
        $tagIds = $source['tag_ids'] ?? [];
        if (empty($tagIds)) {
            return null;
        }

        $table = $this->db->table(Connection::TABLE_CONTACTS);
        $pivotTable = $this->db->table(Connection::TABLE_CONTACT_TAG);
        $placeholders = implode(',', array_fill(0, count($tagIds), '%s'));

        $channelClause = $channelField ? " AND c.{$channelField} IS NOT NULL AND c.{$channelField} != ''" : '';
        $statusClause = $excludeUnsubscribed
            ? " AND c.status NOT IN ('bounced', 'complained')" . $this->buildChannelOptOutClause($channelField)
            : '';

        return $this->db->prepare(
            "SELECT DISTINCT c.id, c.phone, c.email, c.first_name, c.last_name, c.custom_fields FROM {$table} c INNER JOIN {$pivotTable} ct ON c.id = ct.contact_id WHERE ct.tag_id IN ({$placeholders}){$channelClause}{$statusClause}",
            ...$tagIds,
        );
    }

    private function buildRolesQuery(array $source, ?string $channelField): ?string
    {
        $roles = $source['roles'] ?? [];
        if (empty($roles)) {
            return null;
        }

        $wpdb = $this->db->wpdb();
        $table = $this->db->table(Connection::TABLE_CONTACTS);
        $usermeta = $wpdb->usermeta;

        $roleClauses = [];
        foreach ($roles as $role) {
            $roleClauses[] = $this->db->prepare("um.meta_value LIKE %s", '%' . $this->db->escLike('"' . $role . '"') . '%');
        }
        $roleWhere = '(' . implode(' OR ', $roleClauses) . ')';

        $channelClause = $channelField ? " AND c.{$channelField} IS NOT NULL AND c.{$channelField} != ''" : '';

        return "SELECT DISTINCT c.id, c.phone, c.email, c.first_name, c.last_name, c.custom_fields FROM {$table} c INNER JOIN {$usermeta} um ON c.wp_user_id = um.user_id AND um.meta_key = '{$wpdb->prefix}capabilities' WHERE {$roleWhere}{$channelClause}";
    }

    private function buildSegmentWhere(array $conditions): string
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
            $subWhere = $this->buildSegmentWhere($group);
            if ($subWhere && $subWhere !== '1=1') {
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
            'tag'       => $this->buildTagConditionClause($condition),
            default     => null,
        };
    }

    private function buildAttributeClause(array $condition): ?string
    {
        $field = $condition['field'] ?? '';
        $operator = $condition['operator'] ?? 'equals';
        $value = $condition['value'] ?? '';

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

    private function buildTagConditionClause(array $condition): ?string
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
            'has'     => "EXISTS ({$subquery})",
            'not_has' => "NOT EXISTS ({$subquery})",
            default   => null,
        };
    }
}
