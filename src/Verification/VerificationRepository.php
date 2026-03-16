<?php

namespace WSms\Verification;

defined('ABSPATH') || exit;

class VerificationRepository
{
    private const ALLOWED_WHERE_COLUMNS = ['user_id', 'session_id', 'type', 'channel_id', 'identifier'];

    public function insert(array $data): int
    {
        global $wpdb;

        $wpdb->insert($wpdb->prefix . 'wsms_verifications', $data);

        return (int) $wpdb->insert_id;
    }

    /**
     * Find the latest pending (unused) verification matching the given conditions.
     *
     * @param array $where Keyed conditions: user_id, session_id, type, channel_id, identifier.
     */
    public function findLatestPending(array $where): ?object
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wsms_verifications';
        $sql = "SELECT * FROM {$table} WHERE used_at IS NULL";
        $params = [];
        $sql = $this->appendWhereConditions($sql, $where, $params);
        $sql .= ' ORDER BY created_at DESC LIMIT 1';

        $row = $wpdb->get_row(
            empty($params) ? $sql : $wpdb->prepare($sql, ...$params),
        );

        return $row ?: null;
    }

    /**
     * Find a verification by its hashed code and type.
     * Used for token-based lookups (password reset, email verify, magic links).
     */
    public function findByCode(string $hashedCode, string $type): ?object
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wsms_verifications';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE code = %s AND type = %s LIMIT 1",
            $hashedCode,
            $type,
        ));

        return $row ?: null;
    }

    /**
     * Find a verification by hashed code, type, and channel_id.
     * Used for magic link token lookups where channel_id is part of the key.
     */
    public function findByCodeAndChannel(string $hashedCode, string $type, string $channelId): ?object
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wsms_verifications';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE channel_id = %s AND type = %s AND code = %s AND used_at IS NULL LIMIT 1",
            $channelId,
            $type,
            $hashedCode,
        ));

        return $row ?: null;
    }

    /**
     * Atomically increment attempts if below max_attempts.
     *
     * @return bool True if increment succeeded (attempts was below max), false otherwise.
     */
    public function incrementAttempts(int $id): bool
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wsms_verifications';

        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET attempts = attempts + 1 WHERE id = %d AND attempts < max_attempts",
            $id,
        ));

        return $wpdb->rows_affected > 0;
    }

    public function markUsed(int $id): bool
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wsms_verifications';

        $affected = $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET used_at = %s WHERE id = %d AND used_at IS NULL",
            gmdate('Y-m-d H:i:s'),
            $id,
        ));

        return $affected > 0;
    }

    /**
     * Mark a verification as used with final attempt count.
     * Used for atomic verify-and-consume in a single update.
     */
    public function markUsedWithAttempts(int $id, int $attempts): bool
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wsms_verifications';

        $affected = $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET attempts = %d, used_at = %s WHERE id = %d AND used_at IS NULL",
            $attempts,
            gmdate('Y-m-d H:i:s'),
            $id,
        ));

        return $affected > 0;
    }

    /**
     * Update the attempt count for a verification.
     */
    public function updateAttempts(int $id, int $attempts): void
    {
        global $wpdb;

        $wpdb->update(
            $wpdb->prefix . 'wsms_verifications',
            ['attempts' => $attempts],
            ['id' => $id],
        );
    }

    /**
     * Invalidate all pending verifications matching the given conditions.
     *
     * @param array $where Keyed conditions: user_id, session_id, type, channel_id.
     */
    public function invalidatePending(array $where): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wsms_verifications';
        $sql = "UPDATE {$table} SET used_at = %s WHERE used_at IS NULL";
        $params = [gmdate('Y-m-d H:i:s')];
        $sql = $this->appendWhereConditions($sql, $where, $params);

        $wpdb->query($wpdb->prepare($sql, ...$params));
    }

    /**
     * Check if there is a pending verification within the cooldown window.
     *
     * @param array $where Keyed conditions: user_id, session_id, type, channel_id.
     */
    public function hasPendingWithinCooldown(array $where, int $cooldownSeconds): bool
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wsms_verifications';
        $cutoff = gmdate('Y-m-d H:i:s', time() - $cooldownSeconds);

        $sql = "SELECT id FROM {$table} WHERE used_at IS NULL AND created_at > %s";
        $params = [$cutoff];
        $sql = $this->appendWhereConditions($sql, $where, $params);
        $sql .= ' LIMIT 1';

        return (bool) $wpdb->get_row($wpdb->prepare($sql, ...$params));
    }

    public function deleteExpired(): int
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wsms_verifications';

        return (int) $wpdb->query("DELETE FROM {$table} WHERE expires_at < NOW()");
    }

    public function delete(int $id): void
    {
        global $wpdb;

        $wpdb->delete($wpdb->prefix . 'wsms_verifications', ['id' => $id]);
    }

    private function appendWhereConditions(string $sql, array $where, array &$params): string
    {
        foreach ($where as $col => $val) {
            if (!in_array($col, self::ALLOWED_WHERE_COLUMNS, true)) {
                throw new \InvalidArgumentException("Invalid column: {$col}");
            }

            $sql .= " AND {$col} = " . (is_int($val) ? '%d' : '%s');
            $params[] = $val;
        }

        return $sql;
    }
}
