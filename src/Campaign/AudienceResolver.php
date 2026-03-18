<?php

namespace WSms\Campaign;

use WSms\Contact\Contracts\SegmentEvaluatorInterface;

defined('ABSPATH') || exit;

class AudienceResolver
{
    public function __construct(
        private readonly SegmentEvaluatorInterface $segmentEvaluator,
    ) {
    }

    /**
     * Resolve audience to a paginated batch of recipients using cursor-based pagination.
     */
    public function resolve(array $audience, string $channel, int $batchSize = 500, ?string $afterId = null): AudienceBatch
    {
        global $wpdb;

        $channelField = $this->getChannelField($channel);
        $excludeUnsubscribed = $audience['exclude_unsubscribed'] ?? true;

        $unionParts = [];
        $manualRecipients = [];

        foreach ($audience['sources'] ?? [] as $source) {
            $sql = match ($source['type'] ?? '') {
                'segment' => $this->buildSegmentQuery($source, $channelField, $excludeUnsubscribed, $wpdb),
                'tags'    => $this->buildTagsQuery($source, $channelField, $excludeUnsubscribed, $wpdb),
                'wp_roles' => $this->buildRolesQuery($source, $channelField, $wpdb),
                'manual'  => null, // handled separately
                default   => null,
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
                $cursorClause = $wpdb->prepare(' AND id > %s', $afterId);
            }

            $query = "SELECT * FROM ({$unionSql}) AS audience WHERE 1=1 {$cursorClause} ORDER BY id ASC LIMIT %d";
            $rows = $wpdb->get_results($wpdb->prepare($query, $batchSize + 1), ARRAY_A) ?: [];

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
        global $wpdb;

        $channelField = $this->getChannelField($channel);
        $excludeUnsubscribed = $audience['exclude_unsubscribed'] ?? true;

        $unionParts = [];
        $manualCount = 0;

        foreach ($audience['sources'] ?? [] as $source) {
            $sql = match ($source['type'] ?? '') {
                'segment'  => $this->buildSegmentQuery($source, $channelField, $excludeUnsubscribed, $wpdb),
                'tags'     => $this->buildTagsQuery($source, $channelField, $excludeUnsubscribed, $wpdb),
                'wp_roles' => $this->buildRolesQuery($source, $channelField, $wpdb),
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
            $contactCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM ({$unionSql}) AS audience");
        }

        return $contactCount + $manualCount;
    }

    /**
     * Count contacts that will be skipped due to missing channel field.
     */
    public function countSkipped(array $audience, string $channel): int
    {
        global $wpdb;

        $channelField = $this->getChannelField($channel);
        $excludeUnsubscribed = $audience['exclude_unsubscribed'] ?? true;

        $totalWithField = $this->count($audience, $channel);

        // Count without the channel field filter to get total audience
        $unionParts = [];
        foreach ($audience['sources'] ?? [] as $source) {
            $sql = match ($source['type'] ?? '') {
                'segment'  => $this->buildSegmentQuery($source, null, $excludeUnsubscribed, $wpdb),
                'tags'     => $this->buildTagsQuery($source, null, $excludeUnsubscribed, $wpdb),
                'wp_roles' => $this->buildRolesQuery($source, null, $wpdb),
                default    => null,
            };
            if ($sql) {
                $unionParts[] = $sql;
            }
        }

        $totalWithout = 0;
        if (!empty($unionParts)) {
            $unionSql = implode(' UNION ', $unionParts);
            $totalWithout = (int) $wpdb->get_var("SELECT COUNT(*) FROM ({$unionSql}) AS audience");
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

    private function buildSegmentQuery(array $source, ?string $channelField, bool $excludeUnsubscribed, object $wpdb): string
    {
        $table = $wpdb->prefix . 'wsms_contacts';
        $conditions = $source['conditions'] ?? [];

        $segmentWhere = '1=1';
        if (!empty($conditions)) {
            // Build WHERE clause from segment conditions using the same logic as SegmentEvaluator
            $segmentWhere = $this->buildSegmentWhere($conditions, $wpdb);
        }

        $channelClause = $channelField ? " AND c.{$channelField} IS NOT NULL AND c.{$channelField} != ''" : '';
        $statusClause = $excludeUnsubscribed ? " AND c.status = 'subscribed'" : '';

        return "SELECT DISTINCT c.id, c.phone, c.email, c.first_name, c.last_name, c.custom_fields FROM {$table} c WHERE {$segmentWhere}{$channelClause}{$statusClause}";
    }

    private function buildTagsQuery(array $source, ?string $channelField, bool $excludeUnsubscribed, object $wpdb): ?string
    {
        $tagIds = $source['tag_ids'] ?? [];
        if (empty($tagIds)) {
            return null;
        }

        $table = $wpdb->prefix . 'wsms_contacts';
        $pivotTable = $wpdb->prefix . 'wsms_contact_tag';
        $placeholders = implode(',', array_fill(0, count($tagIds), '%s'));

        $channelClause = $channelField ? " AND c.{$channelField} IS NOT NULL AND c.{$channelField} != ''" : '';
        $statusClause = $excludeUnsubscribed ? " AND c.status = 'subscribed'" : '';

        return $wpdb->prepare(
            "SELECT DISTINCT c.id, c.phone, c.email, c.first_name, c.last_name, c.custom_fields FROM {$table} c INNER JOIN {$pivotTable} ct ON c.id = ct.contact_id WHERE ct.tag_id IN ({$placeholders}){$channelClause}{$statusClause}",
            ...$tagIds,
        );
    }

    private function buildRolesQuery(array $source, ?string $channelField, object $wpdb): ?string
    {
        $roles = $source['roles'] ?? [];
        if (empty($roles)) {
            return null;
        }

        $table = $wpdb->prefix . 'wsms_contacts';
        $usermeta = $wpdb->usermeta;

        // Join contacts to WP users via wp_user_id, filter by role in usermeta
        $roleClauses = [];
        foreach ($roles as $role) {
            $roleClauses[] = $wpdb->prepare("um.meta_value LIKE %s", '%' . $wpdb->esc_like('"' . $role . '"') . '%');
        }
        $roleWhere = '(' . implode(' OR ', $roleClauses) . ')';

        $channelClause = $channelField ? " AND c.{$channelField} IS NOT NULL AND c.{$channelField} != ''" : '';

        return "SELECT DISTINCT c.id, c.phone, c.email, c.first_name, c.last_name, c.custom_fields FROM {$table} c INNER JOIN {$usermeta} um ON c.wp_user_id = um.user_id AND um.meta_key = '{$wpdb->prefix}capabilities' WHERE {$roleWhere}{$channelClause}";
    }

    private function buildSegmentWhere(array $conditions, object $wpdb): string
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
            $subWhere = $this->buildSegmentWhere($group, $wpdb);
            if ($subWhere && $subWhere !== '1=1') {
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
            'tag'       => $this->buildTagConditionClause($condition, $wpdb),
            default     => null,
        };
    }

    private function buildAttributeClause(array $condition, object $wpdb): ?string
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

    private function buildTagConditionClause(array $condition, object $wpdb): ?string
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
            'has'     => "EXISTS ({$subquery})",
            'not_has' => "NOT EXISTS ({$subquery})",
            default   => null,
        };
    }
}
