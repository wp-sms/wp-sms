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
    public function update(array $data = []): int
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

    /**
     * Find a single row by WHERE conditions.
     */
    protected function findRow(array $where)
    {
        $sql = "SELECT * FROM {$this->table} WHERE ";
        $conditions = [];
        $values = [];
        
        foreach ($where as $column => $value) {
            if ($value === null) {
                $conditions[] = "`{$column}` IS NULL";
            } else {
                $conditions[] = "`{$column}` = %s";
                $values[] = $value;
            }
        }
        
        $sql .= implode(' AND ', $conditions);
        $sql .= ' LIMIT 1';
        if (!empty($values)) {
            $sql = $this->db->prepare($sql, ...$values);
        }
        
        $result = $this->db->get_row($sql, ARRAY_A);
        return $result ?: null;
    }

    /**
     * Find all rows by WHERE conditions.
     */
    protected function findRows(array $where = [], int $limit = 0, string $orderBy = ''): array
    {
        $sql = "SELECT * FROM {$this->table}";
        $values = [];
        
        if (!empty($where)) {
            $sql .= " WHERE ";
            $conditions = [];
            
            foreach ($where as $column => $value) {
                if ($value === null) {
                    $conditions[] = "`{$column}` IS NULL";
                } else {
                    $conditions[] = "`{$column}` = %s";
                    $values[] = $value;
                }
            }
            
            $sql .= implode(' AND ', $conditions);
        }
        
        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }
        
        if ($limit > 0) {
            $sql .= " LIMIT {$limit}";
        }
        
        if (!empty($values)) {
            $sql = $this->db->prepare($sql, ...$values);
        }
        
        return $this->db->get_results($sql, ARRAY_A) ?: [];
    }



    /**
     * Static proxy for find().
     */
    public static function find(array $where)
    {
        return (new static())->findRow($where);
    }

    /**
     * Static proxy for findAll().
     */
    public static function findAll(array $where = [], int $limit = 0, string $orderBy = ''): array
    {
        return (new static())->findRows($where, $limit, $orderBy);
    }

    public static function exists(array $where): bool
    {
        return static::find($where) !== null;
    }
}
