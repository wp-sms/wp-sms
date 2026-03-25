<?php

namespace WSms\Database;

use WSms\Exception\PersistenceException;

defined('ABSPATH') || exit;

/**
 * Thin wrapper around $wpdb that centralizes table prefixing,
 * query preparation, and error checking.
 *
 * @since 8.0
 */
class Connection
{
    public const TABLE_CONTACTS        = 'wsms_contacts';
    public const TABLE_CONTACT_TAG     = 'wsms_contact_tag';
    public const TABLE_TAGS            = 'wsms_tags';
    public const TABLE_LISTS           = 'wsms_lists';
    public const TABLE_CAMPAIGNS       = 'wsms_campaigns';
    public const TABLE_FLOWS           = 'wsms_flows';
    public const TABLE_FLOW_EXECUTIONS = 'wsms_flow_executions';
    public const TABLE_VERIFICATIONS   = 'wsms_verifications';
    public const TABLE_USER_FACTORS    = 'wsms_user_factors';
    public const TABLE_AUTH_LOGS       = 'wsms_auth_logs';
    public const TABLE_MESSAGE_LOGS    = 'wsms_message_logs';

    private \wpdb $wpdb;

    public function __construct(?\wpdb $wpdb = null)
    {
        $this->wpdb = $wpdb ?? $GLOBALS['wpdb'];
    }

    public function table(string $name): string
    {
        return $this->wpdb->prefix . $name;
    }

    public function escLike(string $value): string
    {
        return $this->wpdb->esc_like($value);
    }

    /**
     * Insert a row.
     *
     * @param array<string, mixed> $data Column => value pairs.
     * @throws PersistenceException on actual DB error.
     */
    public function insert(string $table, array $data): void
    {
        if ($this->wpdb->insert($this->table($table), $data) === false) {
            throw new PersistenceException("Insert into {$table} failed: {$this->wpdb->last_error}");
        }
    }

    public function insertId(): int
    {
        return (int) $this->wpdb->insert_id;
    }

    /**
     * Update rows. Returns number of rows changed.
     *
     * 0 means no rows matched (not an error).
     *
     * @param array<string, mixed> $data  Column => value pairs to set.
     * @param array<string, mixed> $where Column => value pairs for WHERE clause.
     * @throws PersistenceException only on actual DB error (false return).
     */
    public function update(string $table, array $data, array $where): int
    {
        $result = $this->wpdb->update($this->table($table), $data, $where);
        if ($result === false) {
            throw new PersistenceException("Update {$table} failed: {$this->wpdb->last_error}");
        }
        return $result;
    }

    /** @param array<string, mixed> $where */
    public function delete(string $table, array $where): int
    {
        return (int) $this->wpdb->delete($this->table($table), $where);
    }

    /** @return array<string, mixed>|null */
    public function getRow(string $sql, mixed ...$args): ?array
    {
        $prepared = empty($args) ? $sql : $this->wpdb->prepare($sql, ...$args);
        $row = $this->wpdb->get_row($prepared, ARRAY_A);
        return $row !== null ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    public function getResults(string $sql, mixed ...$args): array
    {
        $prepared = empty($args) ? $sql : $this->wpdb->prepare($sql, ...$args);
        return $this->wpdb->get_results($prepared, ARRAY_A) ?: [];
    }

    public function getVar(string $sql, mixed ...$args): mixed
    {
        $prepared = empty($args) ? $sql : $this->wpdb->prepare($sql, ...$args);
        return $this->wpdb->get_var($prepared);
    }

    /**
     * Execute a raw query. Returns affected rows count.
     *
     * @throws PersistenceException on actual DB error (false return).
     */
    public function query(string $sql, mixed ...$args): int
    {
        $prepared = empty($args) ? $sql : $this->wpdb->prepare($sql, ...$args);
        $result = $this->wpdb->query($prepared);
        if ($result === false) {
            throw new PersistenceException("Query failed: {$this->wpdb->last_error}");
        }
        return (int) $result;
    }

    public function rowsAffected(): int
    {
        return (int) $this->wpdb->rows_affected;
    }

    public function lastError(): string
    {
        return $this->wpdb->last_error;
    }

    /**
     * Prepare a SQL fragment with format strings.
     * Delegates to wpdb::prepare() for building SQL fragments
     * (e.g., WHERE clauses, subqueries) that will be composed into larger queries.
     */
    public function prepare(string $sql, mixed ...$args): string
    {
        return $this->wpdb->prepare($sql, ...$args);
    }

    /**
     * Get a single column from query results.
     * @return list<string>
     */
    public function getCol(string $sql, mixed ...$args): array
    {
        $prepared = empty($args) ? $sql : $this->wpdb->prepare($sql, ...$args);
        /** @var list<string> */
        return $this->wpdb->get_col($prepared) ?: [];
    }

    public function wpdb(): \wpdb
    {
        return $this->wpdb;
    }
}
