<?php

namespace WP_SMS\Contracts\Abstracts;

use wpdb;

/**
 * BaseModel provides simple DB operations for models.
 *
 * @property int|null $id
 */
abstract class AbstractBaseModel
{
    /**
     * Primary key (optional).
     * @var int|null
     */
    public $id;

    /**
     * WordPress DB instance.
     * @var wpdb
     */
    protected $db;

    /**
     * Fully-qualified table name.
     * @var string
     */
    protected $table;

    /**
     * BaseModel constructor.
     *
     * @param wpdb|null $db Optional custom db handler.
     */
    public function __construct($db = null)
    {
        global $wpdb;
        $this->db = $db ?: $wpdb;
        $this->table = static::getTableName();
    }

    /**
     * Return full table name including prefix.
     */
    abstract protected static function getTableName(): string;

    /**
     * Utility to prefix table names.
     */
    protected static function table(string $suffix): string
    {
        global $wpdb;
        return $wpdb->prefix . $suffix;
    }

    /**
     * Get a single record by WHERE clause.
     */
    public function find(array $where)
    {
        $sql = "SELECT * FROM {$this->table} WHERE ";
        $clauses = [];
        $values = [];

        foreach ($where as $col => $val) {
            $clauses[] = "$col = " . $this->determineFormat($val);
            $values[] = $val;
        }

        $sql .= implode(' AND ', $clauses) . ' LIMIT 1';
        return $this->db->get_row($this->db->prepare($sql, ...$values), ARRAY_A);
    }

    /**
     * Find multiple rows.
     */
    public function findAll(array $where = [], int $limit = null, string $orderBy = null): array
    {
        $sql = "SELECT * FROM {$this->table}";
        $values = [];

        if ($where) {
            $clauses = [];
            foreach ($where as $col => $val) {
                $clauses[] = "$col = " . $this->determineFormat($val);
                $values[] = $val;
            }
            $sql .= ' WHERE ' . implode(' AND ', $clauses);
        }

        if ($orderBy) {
            $sql .= ' ORDER BY ' . esc_sql($orderBy);
        }

        if ($limit) {
            $sql .= ' LIMIT ' . intval($limit);
        }

        return $this->db->get_results($this->db->prepare($sql, ...$values), ARRAY_A);
    }

    /**
     * Insert a new row.
     */
    public static function insert(array $data): int
    {
        $instance = new static();
        $instance->db->insert(static::getTableName(), $data);
        return (int) $instance->db->insert_id;
    }

    /**
     * Update rows by WHERE.
     */
    public static function updateBy(array $data, array $where): int
    {
        $instance = new static();
        return $instance->db->update(static::getTableName(), $data, $where);
    }

    /**
     * Delete rows by WHERE.
     */
    public static function deleteBy(array $where): int
    {
        $instance = new static();
        return $instance->db->delete(static::getTableName(), $where);
    }

    /**
     * Update current object in the DB.
     */
    public function update(array $data = null): int
    {
        if (!isset($this->id)) {
            throw new \RuntimeException('Cannot update: id is not set.');
        }

        if ($data === null) {
            $data = get_object_vars($this);
            unset($data['id']);
        }

        return $this->db->update($this->table, $data, ['id' => $this->id]);
    }

    /**
     * Delete current object from the DB.
     */
    public function delete(): int
    {
        if (!isset($this->id)) {
            throw new \RuntimeException('Cannot delete: id is not set.');
        }

        return $this->db->delete($this->table, ['id' => $this->id]);
    }

    /**
     * Determine SQL format string based on PHP type.
     */
    protected function determineFormat($val): string
    {
        if (is_int($val)) {
            return '%d';
        }

        if (is_float($val)) {
            return '%f';
        }

        return '%s';
    }
}
