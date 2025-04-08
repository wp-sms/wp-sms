<?php

namespace WP_SMS\Utils;

use mysqli_result;
use WP_SMS\Traits\TransientCacheTrait;
use WP_SMS\Utils\DBUtil as DB;
use InvalidArgumentException;
use wpdb;

class Query
{
    use TransientCacheTrait;

    /** @var wpdb */
    protected $db;

    /**
     * @var array
     */
    private $queries = [];
    /**
     * @var
     */
    private $operation;
    /**
     * @var
     */
    private $table;
    /**
     * @var string
     */
    private $fields = '*';
    /**
     * @var
     */
    private $subQuery;
    /**
     * @var
     */
    private $orderClause;
    /**
     * @var
     */
    private $groupByClause;
    /**
     * @var
     */
    private $limitClause;
    /**
     * @var string
     */
    private $whereRelation = 'AND';
    /**
     * @var array
     */
    private $setClauses = [];
    /**
     * @var array
     */
    private $joinClauses = [];
    /**
     * @var array
     */
    private $whereClauses = [];
    /**
     * @var array
     */
    private $rawWhereClause = [];
    /**
     * @var array
     */
    private $valuesToPrepare = [];
    /**
     * @var bool
     */
    private $allowCaching = false;
    /**
     * @var
     */
    private $decorator;

    /**
     *
     */
    public function __construct()
    {
        global $wpdb;
        $this->db = $wpdb;
    }

    /**
     * @param $fields
     * @return self
     */
    public static function select($fields = '*')
    {
        $instance            = new self();
        $instance->operation = 'select';
        $instance->fields    = is_array($fields) ? implode(', ', $fields) : $fields;
        return $instance;
    }

    /**
     * @param $table
     * @return self
     */
    public static function update($table)
    {
        $instance            = new self();
        $instance->operation = 'update';
        $instance->table     = $instance->getTable($table);
        return $instance;
    }

    /**
     * @param $table
     * @return self
     */
    public static function insert($table)
    {
        $instance            = new self();
        $instance->operation = 'insert';
        $instance->table     = $instance->getTable($table);
        return $instance;
    }

    /**
     * @param $table
     * @return self
     */
    public static function delete($table)
    {
        $instance            = new self();
        $instance->operation = 'delete';
        $instance->table     = $instance->getTable($table);
        return $instance;
    }

    /**
     * @param $queries
     * @return self
     */
    public static function union($queries)
    {
        $instance            = new self();
        $instance->operation = 'union';
        $instance->queries   = $queries;
        return $instance;
    }

    /**
     * @param $table
     * @return $this
     */
    public function from($table)
    {
        $this->table = $this->getTable($table);
        return $this;
    }

    /**
     * @param $table
     * @return array|string|null
     */
    private function getTable($table)
    {
        if (DB::table($table)) {
            return DB::table($table);
        }
        return isset($this->db->tables('global')[$table])
            ? $this->db->tables('global')[$table]
            : "{$this->db->prefix}{$table}";
    }

    /**
     * @param $field
     * @param $operator
     * @param $value
     * @return $this
     */
    public function where($field, $operator, $value)
    {
        if (empty($value) && !is_numeric($value)) return $this;

        $condition = $this->generateCondition($field, $operator, $value);
        if (!empty($condition)) {
            $this->whereClauses[]  = $condition['condition'];
            $this->valuesToPrepare = array_merge($this->valuesToPrepare, $condition['values']);
        }
        return $this;
    }

    /**
     * @param $field
     * @param $operator
     * @param $value
     * @return array
     */
    protected function generateCondition($field, $operator, $value)
    {
        $condition = '';
        $values    = [];

        switch (strtoupper($operator)) {
            case '=':
            case '!=':
            case '>':
            case '>=':
            case '<':
            case '<=':
            case 'LIKE':
            case 'NOT LIKE':
                $condition = "$field $operator %s";
                $values[]  = $value;
                break;
            case 'IN':
            case 'NOT IN':
                if (!is_array($value)) $value = explode(',', $value);
                $placeholders = implode(', ', array_fill(0, count($value), '%s'));
                $condition    = "$field $operator ($placeholders)";
                $values       = $value;
                break;
            case 'BETWEEN':
                if (is_array($value) && count($value) === 2) {
                    $condition = "$field BETWEEN %s AND %s";
                    $values    = $value;
                }
                break;
            default:
                throw new InvalidArgumentException("Unsupported operator: $operator");
        }

        return ['condition' => $condition, 'values' => $values];
    }

    /**
     * @return array|mixed
     */
    public function getAll()
    {
        $query = $this->prepareQuery($this->buildQuery(), $this->valuesToPrepare);

        // Use caching if enabled
        if ($this->allowCaching) {
            $cachedResult = $this->getCachedResult($query);
            if ($cachedResult !== false) {
                return $this->maybeDecorate($cachedResult);
            }
        }

        // Fetch from database if not cached
        $result = $this->db->get_results($query);

        // Cache the result if caching is enabled
        if ($this->allowCaching) {
            $this->setCachedResult($query, $result, WEEK_IN_SECONDS); // 7 days
        }

        return $this->maybeDecorate($result);
    }

    /**
     * @param $result
     * @return array|mixed
     */
    private function maybeDecorate($result)
    {
        if (empty($this->decorator) || !class_exists($this->decorator)) {
            return $result;
        }
        $decoratedResult = [];
        if (is_array($result)) {
            foreach ($result as $item) {
                $decoratedResult[] = new $this->decorator($item);
            }
        }
        if (is_object($result)) {
            $decoratedResult = new $this->decorator($result);
        }
        return $decoratedResult;
    }

    /**
     * @return string
     */
    protected function buildQuery()
    {
        switch ($this->operation) {
            case 'select':
                return $this->selectQuery();
            case 'update':
                return $this->updateQuery();
            case 'insert':
                return $this->insertQuery();
            case 'delete':
                return $this->deleteQuery();
            case 'union':
                return $this->unionQuery();
            default:
                throw new InvalidArgumentException("Unknown query operation: $this->operation");
        }
    }

    /**
     * @return string
     */
    protected function selectQuery()
    {
        $query = "SELECT $this->fields FROM $this->table";
        if (!empty($this->joinClauses)) $query .= ' ' . implode(' ', $this->joinClauses);
        if (!empty($this->whereClauses)) $query .= ' WHERE ' . implode(" $this->whereRelation ", $this->whereClauses);
        if (!empty($this->groupByClause)) $query .= ' ' . $this->groupByClause;
        if (!empty($this->orderClause)) $query .= ' ' . $this->orderClause;
        if (!empty($this->limitClause)) $query .= ' ' . $this->limitClause;
        return $query;
    }

    /**
     * @return string
     */
    protected function updateQuery()
    {
        if (empty($this->setClauses)) throw new InvalidArgumentException("No SET clauses for update query");
        $query = "UPDATE $this->table SET " . implode(', ', $this->setClauses);
        if (!empty($this->whereClauses)) $query .= ' WHERE ' . implode(" $this->whereRelation ", $this->whereClauses);
        return $query;
    }

    /**
     * @return string
     */
    protected function insertQuery()
    {
        if (empty($this->setClauses)) {
            throw new InvalidArgumentException("No values provided for insert query");
        }

        $fields = [];
        $values = [];
        foreach ($this->setClauses as $clause) {
            list($field, $value) = explode(' = ', $clause);
            $fields[] = $field;
            $values[] = $value;
        }

        $fields = implode(', ', $fields);
        $values = implode(', ', $values);

        return "INSERT INTO $this->table ($fields) VALUES ($values)";
    }

    /**
     * @return string
     */
    protected function deleteQuery()
    {
        $query = "DELETE FROM $this->table";
        if (!empty($this->whereClauses)) {
            $query .= ' WHERE ' . implode(" $this->whereRelation ", $this->whereClauses);
        }
        return $query;
    }

    /**
     * @return string
     */
    protected function unionQuery()
    {
        foreach ($this->queries as $key => $value) {
            $this->queries[$key] = "($value)";
        }
        $query = implode(' UNION ', $this->queries);
        if (!empty($this->orderClause)) {
            $query .= ' ' . $this->orderClause;
        }
        if (!empty($this->limitClause)) {
            $query .= ' ' . $this->limitClause;
        }
        return $query;
    }

    /**
     * @return bool|int|mixed|mysqli_result|null
     */
    public function execute()
    {
        $query  = $this->prepareQuery($this->buildQuery(), $this->valuesToPrepare);
        $result = $this->db->query($query);

        // Clear cache for this table if it's a write operation
        if ($this->operation !== 'select' && $result !== false) {
            $this->clearCacheForTable($this->table);
        }

        return $result;
    }

    /**
     * @param $query
     * @param $args
     * @return mixed|string|null
     */
    protected function prepareQuery($query, $args = [])
    {
        return (strpos($query, '%') !== false) ? $this->db->prepare($query, $args) : $query;
    }

    /**
     * @param array $data
     * @return $this
     */
    public function set(array $data)
    {
        foreach ($data as $field => $value) {
            $this->setClauses[]      = "$field = %s";
            $this->valuesToPrepare[] = $value;
        }
        return $this;
    }

    /**
     * @param array $data
     * @return $this
     */
    public function values(array $data)
    {
        foreach ($data as $field => $value) {
            $this->setClauses[]      = "$field = %s";
            $this->valuesToPrepare[] = $value;
        }
        return $this;
    }

    /**
     * @param $table
     * @param $first
     * @param $operator
     * @param $second
     * @param $type
     * @return $this
     */
    public function join($table, $first, $operator, $second, $type = 'INNER')
    {
        $table               = $this->getTable($table);
        $this->joinClauses[] = "$type JOIN $table ON $first $operator $second";
        return $this;
    }

    /**
     * @param $field
     * @param $direction
     * @return $this
     */
    public function orderBy($field, $direction = 'ASC')
    {
        $this->orderClause = "ORDER BY $field $direction";
        return $this;
    }

    /**
     * @param $field
     * @return $this
     */
    public function groupBy($field)
    {
        $this->groupByClause = "GROUP BY $field";
        return $this;
    }

    /**
     * @param $limit
     * @param $offset
     * @return $this
     */
    public function limit($limit, $offset = null)
    {
        $this->limitClause = "LIMIT $limit" . ($offset ? " OFFSET $offset" : '');
        return $this;
    }

    /**
     * @param $allow
     * @return $this
     */
    public function allowCaching($allow = true)
    {
        $this->allowCaching = $allow;
        return $this;
    }

    /**
     * @param $decorator
     * @return $this
     */
    public function setDecorator($decorator)
    {
        $this->decorator = $decorator;
        return $this;
    }

    /**
     * Clear all cached queries related to a specific table.
     *
     * @param string $table
     * @return void
     */
    private function clearCacheForTable($table)
    {
        $cacheKeyPrefix = $this->getCacheKey("table:$table");
        delete_transient($cacheKeyPrefix);
    }
}