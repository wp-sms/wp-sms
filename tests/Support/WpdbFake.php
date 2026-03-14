<?php

namespace WSms\Tests\Support;

/**
 * Assertable wpdb replacement that tracks inserts/updates and stores
 * verification rows for multi-step flow testing.
 */
class WpdbFake
{
    public string $prefix = 'wp_';
    public int $insert_id = 0;

    /** @var array<int, array{table: string, data: array}> */
    public array $inserts = [];

    /** @var array<int, array{table: string, data: array, where: array}> */
    public array $updates = [];

    /** @var string[] Raw queries passed to query() */
    public array $queries = [];

    /** @var array<int, object> In-memory verification rows (keyed by auto-increment id) */
    private array $verifications = [];

    /** @var object[] Manually pre-loaded rows for get_row() */
    private array $preloadedRows = [];

    /**
     * Pre-load a row to be returned by get_row().
     * Pre-loaded rows take priority over auto-stored verifications.
     */
    public function withRow(object $row): self
    {
        $this->preloadedRows[] = $row;

        return $this;
    }

    public function insert($table, $data, $format = null): bool
    {
        $this->insert_id++;
        $this->inserts[] = ['table' => $table, 'data' => $data];

        if (str_contains($table, 'wsms_verifications')) {
            $row = (object) $data;
            $row->id = $this->insert_id;
            $row->used_at = $data['used_at'] ?? null;
            $row->attempts = $data['attempts'] ?? 0;
            $row->max_attempts = $data['max_attempts'] ?? 3;
            $row->expires_at = $data['expires_at'] ?? gmdate('Y-m-d H:i:s', time() + 3600);
            $row->created_at = $data['created_at'] ?? gmdate('Y-m-d H:i:s');
            $this->verifications[$this->insert_id] = $row;
        }

        return true;
    }

    public function get_row($query): ?object
    {
        // Return pre-loaded rows first (FIFO).
        if (!empty($this->preloadedRows)) {
            return array_shift($this->preloadedRows);
        }

        // Extract query conditions once, then compare per row.
        $conditions = self::parseQueryConditions($query);

        // Search stored verifications (newest first) without copying.
        $keys = array_keys($this->verifications);

        for ($i = count($keys) - 1; $i >= 0; $i--) {
            $row = $this->verifications[$keys[$i]];

            if (self::rowMatchesConditions($row, $conditions)) {
                return clone $row;
            }
        }

        return null;
    }

    public function get_results($query): array
    {
        return [];
    }

    public function get_var($query)
    {
        return 0;
    }

    public function update($table, $data, $where, $format = null, $whereFormat = null): bool
    {
        $this->updates[] = ['table' => $table, 'data' => $data, 'where' => $where];

        if (str_contains($table, 'wsms_verifications')) {
            foreach ($this->verifications as &$row) {
                $match = true;

                foreach ($where as $key => $value) {
                    $rowValue = is_object($row) ? ($row->$key ?? null) : null;

                    if ($rowValue === null || (string) $rowValue !== (string) $value) {
                        $match = false;
                        break;
                    }
                }

                if ($match) {
                    foreach ($data as $key => $value) {
                        $row->$key = $value;
                    }
                }
            }
        }

        return true;
    }

    /** @var int Rows affected by last query() call. */
    public int $rows_affected = 1;

    public function query($query)
    {
        $this->queries[] = $query;
        $this->rows_affected = 0;

        // Handle bulk UPDATE (invalidateVerifications).
        if (preg_match('/UPDATE.*wsms_verifications.*SET\b.*used_at/is', $query)) {
            $conditions = self::parseQueryConditions($query);
            $affected = 0;

            foreach ($this->verifications as &$row) {
                if ($conditions['user_id'] !== null && (int) $row->user_id !== $conditions['user_id']) {
                    continue;
                }

                if ($conditions['session_id'] !== null && ($row->session_id ?? '') !== $conditions['session_id']) {
                    continue;
                }

                if ($conditions['type'] !== null && $row->type !== $conditions['type']) {
                    continue;
                }

                if ($row->used_at !== null) {
                    continue;
                }

                // Handle atomic single-row update (WHERE id = X AND used_at IS NULL).
                if (preg_match('/WHERE\s+.*id\s*=\s*(\d+)/i', $query, $m)) {
                    if ((int) $row->id !== (int) $m[1]) {
                        continue;
                    }
                }

                // Handle attempts update in the same query.
                if (preg_match('/attempts\s*=\s*(\d+)/i', $query, $m)) {
                    $row->attempts = (int) $m[1];
                }

                $row->used_at = gmdate('Y-m-d H:i:s');
                $affected++;
            }

            $this->rows_affected = $affected;
        }

        return $this->rows_affected;
    }

    public function prepare(string $query, ...$args): string
    {
        $result = $query;

        foreach ($args as $arg) {
            $replacement = is_numeric($arg) ? (string) $arg : "'" . addslashes((string) $arg) . "'";
            $result = preg_replace('/%[sd]/', $replacement, $result, 1);
        }

        return $result;
    }

    /**
     * Get all stored verification rows.
     *
     * @return object[]
     */
    public function getVerifications(): array
    {
        return array_values($this->verifications);
    }

    /**
     * Get verifications filtered by type.
     *
     * @return object[]
     */
    public function getVerificationsByType(string $type): array
    {
        return array_values(array_filter(
            $this->verifications,
            fn(object $row) => $row->type === $type,
        ));
    }

    /**
     * Expire a stored verification by ID (for testing expired-token paths).
     */
    public function expireVerification(int $id): void
    {
        if (isset($this->verifications[$id])) {
            $this->verifications[$id]->expires_at = gmdate('Y-m-d H:i:s', time() - 3600);
        }
    }

    /**
     * Mark a stored verification as used by ID (for testing replay protection).
     */
    public function markVerificationUsed(int $id): void
    {
        if (isset($this->verifications[$id])) {
            $this->verifications[$id]->used_at = gmdate('Y-m-d H:i:s');
        }
    }

    /**
     * Set a verification's attempts to its max_attempts (for testing max-attempts paths).
     */
    public function exhaustVerificationAttempts(int $id): void
    {
        if (isset($this->verifications[$id])) {
            $this->verifications[$id]->attempts = $this->verifications[$id]->max_attempts;
        }
    }

    /**
     * Reset all tracked state.
     */
    public function reset(): void
    {
        $this->insert_id = 0;
        $this->inserts = [];
        $this->updates = [];
        $this->queries = [];
        $this->verifications = [];
        $this->preloadedRows = [];
    }

    /**
     * Extract common WHERE clause conditions from a SQL query string.
     * Called once per query, then compared per row.
     */
    private static function parseQueryConditions(string $query): array
    {
        $conditions = [
            'user_id'           => null,
            'session_id'        => null,
            'type'              => null,
            'code'              => null,
            'identifier'        => null,
            'require_unused'    => false,
            'cooldown_seconds'  => null,
        ];

        if (preg_match('/user_id\s*=\s*(\d+)/i', $query, $m)) {
            $conditions['user_id'] = (int) $m[1];
        }

        if (preg_match("/session_id\s*=\s*'([^']+)'/i", $query, $m)) {
            $conditions['session_id'] = $m[1];
        }

        if (preg_match("/type\s*=\s*'([^']+)'/i", $query, $m)) {
            $conditions['type'] = $m[1];
        }

        if (preg_match("/code\s*=\s*'([^']+)'/i", $query, $m)) {
            $conditions['code'] = $m[1];
        }

        if (preg_match("/identifier\s*=\s*'([^']+)'/i", $query, $m)) {
            $conditions['identifier'] = $m[1];
        }

        if (str_contains($query, 'used_at IS NULL')) {
            $conditions['require_unused'] = true;
        }

        if (preg_match('/created_at\s*>\s*DATE_SUB\s*\(\s*NOW\(\)\s*,\s*INTERVAL\s+(\d+)\s+SECOND\s*\)/i', $query, $m)) {
            $conditions['cooldown_seconds'] = (int) $m[1];
        } elseif (preg_match("/created_at\s*>\s*'([^']+)'/i", $query, $m)) {
            $conditions['cooldown_cutoff'] = $m[1];
        }

        return $conditions;
    }

    /**
     * Check if a row matches pre-parsed query conditions.
     */
    private static function rowMatchesConditions(object $row, array $conditions): bool
    {
        if ($conditions['user_id'] !== null && (int) ($row->user_id ?? -1) !== $conditions['user_id']) {
            return false;
        }

        if ($conditions['session_id'] !== null && ($row->session_id ?? '') !== $conditions['session_id']) {
            return false;
        }

        if ($conditions['type'] !== null && ($row->type ?? '') !== $conditions['type']) {
            return false;
        }

        if ($conditions['code'] !== null && ($row->code ?? '') !== $conditions['code']) {
            return false;
        }

        if ($conditions['identifier'] !== null && ($row->identifier ?? '') !== $conditions['identifier']) {
            return false;
        }

        if ($conditions['require_unused'] && ($row->used_at ?? null) !== null) {
            return false;
        }

        if ($conditions['cooldown_seconds'] !== null) {
            $threshold = time() - $conditions['cooldown_seconds'];
            $createdAt = isset($row->created_at) ? strtotime($row->created_at) : 0;

            if ($createdAt <= $threshold) {
                return false;
            }
        }

        if (($conditions['cooldown_cutoff'] ?? null) !== null) {
            $cutoff = strtotime($conditions['cooldown_cutoff']);
            $createdAt = isset($row->created_at) ? strtotime($row->created_at) : 0;

            if ($createdAt <= $cutoff) {
                return false;
            }
        }

        return true;
    }
}
