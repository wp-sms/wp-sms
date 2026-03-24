<?php

namespace WSms\Verification;

use WSms\Database\Connection;

defined('ABSPATH') || exit;

class VerificationRepository
{
    private const ALLOWED_WHERE_COLUMNS = ['user_id', 'session_id', 'type', 'channel_id', 'identifier'];

    public function __construct(private readonly Connection $db) {}

    public function insert(array $data): int
    {
        $this->db->insert(Connection::TABLE_VERIFICATIONS, $data);

        return $this->db->insertId();
    }

    /**
     * Find the latest pending (unused) verification matching the given conditions.
     *
     * @param array $where Keyed conditions: user_id, session_id, type, channel_id, identifier.
     */
    public function findLatestPending(array $where): ?object
    {
        $table = $this->db->table(Connection::TABLE_VERIFICATIONS);
        $sql = "SELECT * FROM {$table} WHERE used_at IS NULL";
        $params = [];
        $sql = $this->appendWhereConditions($sql, $where, $params);
        $sql .= ' ORDER BY created_at DESC LIMIT 1';

        $row = empty($params)
            ? $this->db->getRow($sql)
            : $this->db->getRow($sql, ...$params);

        return $row ? (object) $row : null;
    }

    /**
     * Find a verification by its hashed code and type.
     * Used for token-based lookups (password reset, email verify, magic links).
     */
    public function findByCode(string $hashedCode, string $type): ?object
    {
        $table = $this->db->table(Connection::TABLE_VERIFICATIONS);

        $row = $this->db->getRow(
            "SELECT * FROM {$table} WHERE code = %s AND type = %s LIMIT 1",
            $hashedCode,
            $type,
        );

        return $row ? (object) $row : null;
    }

    /**
     * Find a verification by hashed code, type, and channel_id.
     * Used for magic link token lookups where channel_id is part of the key.
     */
    public function findByCodeAndChannel(string $hashedCode, string $type, string $channelId): ?object
    {
        $table = $this->db->table(Connection::TABLE_VERIFICATIONS);

        $row = $this->db->getRow(
            "SELECT * FROM {$table} WHERE channel_id = %s AND type = %s AND code = %s AND used_at IS NULL LIMIT 1",
            $channelId,
            $type,
            $hashedCode,
        );

        return $row ? (object) $row : null;
    }

    /**
     * Atomically increment attempts if below max_attempts.
     *
     * @return bool True if increment succeeded (attempts was below max), false otherwise.
     */
    public function incrementAttempts(int $id): bool
    {
        $table = $this->db->table(Connection::TABLE_VERIFICATIONS);

        $this->db->query(
            "UPDATE {$table} SET attempts = attempts + 1 WHERE id = %d AND attempts < max_attempts",
            $id,
        );

        return $this->db->rowsAffected() > 0;
    }

    public function markUsed(int $id): bool
    {
        $table = $this->db->table(Connection::TABLE_VERIFICATIONS);

        $affected = $this->db->query(
            "UPDATE {$table} SET used_at = %s WHERE id = %d AND used_at IS NULL",
            gmdate('Y-m-d H:i:s'),
            $id,
        );

        return $affected > 0;
    }

    /**
     * Mark a verification as used with final attempt count.
     * Used for atomic verify-and-consume in a single update.
     */
    public function markUsedWithAttempts(int $id, int $attempts): bool
    {
        $table = $this->db->table(Connection::TABLE_VERIFICATIONS);

        $affected = $this->db->query(
            "UPDATE {$table} SET attempts = %d, used_at = %s WHERE id = %d AND used_at IS NULL",
            $attempts,
            gmdate('Y-m-d H:i:s'),
            $id,
        );

        return $affected > 0;
    }

    /**
     * Update the attempt count for a verification.
     */
    public function updateAttempts(int $id, int $attempts): void
    {
        $this->db->update(
            Connection::TABLE_VERIFICATIONS,
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
        $table = $this->db->table(Connection::TABLE_VERIFICATIONS);
        $sql = "UPDATE {$table} SET used_at = %s WHERE used_at IS NULL";
        $params = [gmdate('Y-m-d H:i:s')];
        $sql = $this->appendWhereConditions($sql, $where, $params);

        $this->db->query($sql, ...$params);
    }

    /**
     * Check if there is a pending verification within the cooldown window.
     *
     * @param array $where Keyed conditions: user_id, session_id, type, channel_id.
     */
    public function hasPendingWithinCooldown(array $where, int $cooldownSeconds): bool
    {
        $table = $this->db->table(Connection::TABLE_VERIFICATIONS);
        $cutoff = gmdate('Y-m-d H:i:s', time() - $cooldownSeconds);

        $sql = "SELECT id FROM {$table} WHERE used_at IS NULL AND created_at > %s";
        $params = [$cutoff];
        $sql = $this->appendWhereConditions($sql, $where, $params);
        $sql .= ' LIMIT 1';

        return (bool) $this->db->getRow($sql, ...$params);
    }

    public function deleteExpired(): int
    {
        $table = $this->db->table(Connection::TABLE_VERIFICATIONS);

        return $this->db->query("DELETE FROM {$table} WHERE expires_at < NOW()");
    }

    public function delete(int $id): void
    {
        $this->db->delete(Connection::TABLE_VERIFICATIONS, ['id' => $id]);
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
