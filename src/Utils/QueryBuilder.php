<?php
namespace WP_SMS\Utils;

/**
 * Query Builder for complex database queries.
 * Supports filtering, sorting, pagination, and aggregation.
 */
class QueryBuilder
{
    /**
     * @var AbstractBaseModel
     */
    protected $model;

    /**
     * @var wpdb
     */
    protected $db;

    /**
     * @var string
     */
    protected $table;

    /**
     * @var array
     */
    protected $wheres = [];

    /**
     * @var array
     */
    protected $orderBy = [];

    /**
     * @var int|null
     */
    protected $limit;

    /**
     * @var int|null
     */
    protected $offset;

    /**
     * @var array
     */
    protected $groupBy = [];

    /**
     * @var array
     */
    protected $select = ['*'];

    /**
     * QueryBuilder constructor.
     * 
     * @param AbstractBaseModel $model
     */
    public function __construct($model)
    {
        $this->model = $model;
        $this->db = $model->getDb();
        $this->table = $model->getTable();
    }

    /**
     * Add WHERE condition.
     * 
     * @param string $column
     * @param mixed $operator
     * @param mixed $value
     * @return $this
     */
    public function where($column, $operator, $value = null)
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
        ];

        return $this;
    }

    /**
     * Add WHERE BETWEEN condition.
     * 
     * @param string $column
     * @param mixed $start
     * @param mixed $end
     * @return $this
     */
    public function whereBetween($column, $start, $end)
    {
        $this->wheres[] = [
            'type' => 'between',
            'column' => $column,
            'start' => $start,
            'end' => $end,
        ];

        return $this;
    }

    /**
     * Add WHERE IN condition.
     * 
     * @param string $column
     * @param array $values
     * @return $this
     */
    public function whereIn($column, array $values)
    {
        if (empty($values)) {
            return $this;
        }

        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'values' => $values,
        ];

        return $this;
    }

    /**
     * Add WHERE LIKE condition.
     * 
     * @param string $column
     * @param string $pattern
     * @return $this
     */
    public function whereLike($column, $pattern)
    {
        $this->wheres[] = [
            'type' => 'like',
            'column' => $column,
            'pattern' => $pattern,
        ];

        return $this;
    }

    /**
     * Add WHERE IS NULL condition.
     * 
     * @param string $column
     * @return $this
     */
    public function whereNull($column)
    {
        $this->wheres[] = [
            'type' => 'null',
            'column' => $column,
        ];

        return $this;
    }

    /**
     * Add WHERE IS NOT NULL condition.
     * 
     * @param string $column
     * @return $this
     */
    public function whereNotNull($column)
    {
        $this->wheres[] = [
            'type' => 'not_null',
            'column' => $column,
        ];

        return $this;
    }

    /**
     * Add OR WHERE conditions (accepts array or callback).
     * 
     * @param callable|array $conditions
     * @return $this
     */
    public function orWhere($conditions)
    {
        if (is_callable($conditions)) {
            $subQuery = new static($this->model);
            $conditions($subQuery);
            $this->wheres[] = [
                'type' => 'or_group',
                'conditions' => $subQuery->wheres,
            ];
        } elseif (is_array($conditions)) {
            $this->wheres[] = [
                'type' => 'or_group',
                'conditions' => $conditions,
            ];
        }

        return $this;
    }

    /**
     * Add multiple ORDER BY clauses.
     * 
     * @param array $sorts Array of ['column' => 'ASC|DESC']
     * @return $this
     */
    public function orderByMultiple(array $sorts)
    {
        foreach ($sorts as $column => $direction) {
            $this->orderBy($column, $direction);
        }

        return $this;
    }

    /**
     * Add ORDER BY clause.
     * 
     * @param string $column
     * @param string $direction
     * @return $this
     */
    public function orderBy($column, $direction = 'ASC')
    {
        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'])) {
            $direction = 'ASC';
        }

        $this->orderBy[] = [
            'column' => $column,
            'direction' => $direction,
        ];

        return $this;
    }

    /**
     * Set LIMIT.
     * 
     * @param int $limit
     * @return $this
     */
    public function limit($limit)
    {
        $this->limit = (int) $limit;
        return $this;
    }

    /**
     * Set OFFSET.
     * 
     * @param int $offset
     * @return $this
     */
    public function offset($offset)
    {
        $this->offset = (int) $offset;
        return $this;
    }

    /**
     * Add GROUP BY clause.
     * 
     * @param string|array $columns
     * @return $this
     */
    public function groupBy($columns)
    {
        if (!is_array($columns)) {
            $columns = [$columns];
        }

        $this->groupBy = array_merge($this->groupBy, $columns);
        return $this;
    }

    /**
     * Select specific columns.
     * 
     * @param array|string $columns
     * @return $this
     */
    public function select($columns)
    {
        if (!is_array($columns)) {
            $columns = [$columns];
        }

        $this->select = $columns;
        return $this;
    }

    /**
     * Paginate results.
     * 
     * @param int $page
     * @param int $perPage
     * @return array ['rows' => [], 'totalCount' => 0, 'page' => 1, 'perPage' => 50, 'totalPages' => 1]
     */
    public function paginate($page = 1, $perPage = 50)
    {
        $page = max(1, (int) $page);
        $perPage = max(1, min(1000, (int) $perPage));

        // Get total count
        $totalCount = $this->count();

        // Calculate offset
        $this->limit($perPage);
        $this->offset(($page - 1) * $perPage);

        // Get rows
        $rows = $this->get();

        return [
            'rows' => $rows,
            'totalCount' => $totalCount,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => (int) ceil($totalCount / $perPage),
        ];
    }

    /**
     * Execute query and return results.
     * 
     * @return array
     */
    public function get()
    {
        $sql = $this->buildSelectQuery();
        $prepared = $this->prepareQuery($sql);

        return $this->db->get_results($prepared, ARRAY_A) ?: [];
    }

    /**
     * Get first result.
     * 
     * @return array|null
     */
    public function first()
    {
        $this->limit(1);
        $results = $this->get();

        return !empty($results) ? $results[0] : null;
    }

    /**
     * Get count of matching rows.
     * 
     * @return int
     */
    public function count()
    {
        $originalSelect = $this->select;
        $this->select = ['COUNT(*) as count'];

        $sql = $this->buildSelectQuery(true);
        $prepared = $this->prepareQuery($sql);

        $this->select = $originalSelect;

        $result = $this->db->get_var($prepared);
        return (int) $result;
    }

    /**
     * Execute aggregate function.
     * 
     * @param string $function COUNT, SUM, AVG, MIN, MAX
     * @param string $column
     * @return mixed
     */
    public function aggregate($function, $column = '*')
    {
        $function = strtoupper($function);
        $allowedFunctions = ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX'];

        if (!in_array($function, $allowedFunctions)) {
            return 0;
        }

        $originalSelect = $this->select;
        $this->select = ["{$function}({$column}) as aggregate"];

        $sql = $this->buildSelectQuery(true);
        $prepared = $this->prepareQuery($sql);

        $this->select = $originalSelect;

        return $this->db->get_var($prepared);
    }

    /**
     * Build SELECT query.
     * 
     * @param bool $skipLimitOffset
     * @return string
     */
    protected function buildSelectQuery($skipLimitOffset = false)
    {
        $sql = "SELECT ";

        // SELECT columns
        if (empty($this->select)) {
            $sql .= "*";
        } else {
            $sql .= implode(', ', array_map(function ($col) {
                if ($col === '*' || strpos($col, '(') !== false || strpos($col, ' as ') !== false || strpos($col, ' AS ') !== false) {
                    return $col;
                }
                return "`{$col}`";
            }, $this->select));
        }

        $sql .= " FROM {$this->table}";

        // WHERE clauses
        if (!empty($this->wheres)) {
            $whereClause = $this->buildWhereClause();
            if (!empty($whereClause)) {
                $sql .= " WHERE " . $whereClause;
            }
        }

        // GROUP BY
        if (!empty($this->groupBy)) {
            $sql .= " GROUP BY " . implode(', ', array_map(function ($col) {
                return "`{$col}`";
            }, $this->groupBy));
        }

        // ORDER BY
        if (!empty($this->orderBy)) {
            $sql .= " ORDER BY " . implode(', ', array_map(function ($order) {
                return "`{$order['column']}` {$order['direction']}";
            }, $this->orderBy));
        }

        // LIMIT and OFFSET
        if (!$skipLimitOffset) {
            if ($this->limit !== null) {
                $sql .= " LIMIT {$this->limit}";
            }

            if ($this->offset !== null) {
                $sql .= " OFFSET {$this->offset}";
            }
        }

        return $sql;
    }

    /**
     * Build WHERE clause from conditions.
     * 
     * @return string
     */
    protected function buildWhereClause()
    {
        $conditions = [];

        foreach ($this->wheres as $where) {
            switch ($where['type']) {
                case 'basic':
                    $conditions[] = "`{$where['column']}` {$where['operator']} %s";
                    break;

                case 'between':
                    $conditions[] = "`{$where['column']}` BETWEEN %s AND %s";
                    break;

                case 'in':
                    $placeholders = implode(', ', array_fill(0, count($where['values']), '%s'));
                    $conditions[] = "`{$where['column']}` IN ({$placeholders})";
                    break;

                case 'like':
                    $conditions[] = "`{$where['column']}` LIKE %s";
                    break;

                case 'null':
                    $conditions[] = "`{$where['column']}` IS NULL";
                    break;

                case 'not_null':
                    $conditions[] = "`{$where['column']}` IS NOT NULL";
                    break;

                case 'or_group':
                    // Build sub-conditions
                    $subConditions = [];
                    foreach ($where['conditions'] as $subWhere) {
                        if ($subWhere['type'] === 'basic') {
                            $subConditions[] = "`{$subWhere['column']}` {$subWhere['operator']} %s";
                        } elseif ($subWhere['type'] === 'like') {
                            $subConditions[] = "`{$subWhere['column']}` LIKE %s";
                        }
                    }
                    if (!empty($subConditions)) {
                        $conditions[] = '(' . implode(' OR ', $subConditions) . ')';
                    }
                    break;
            }
        }

        return implode(' AND ', $conditions);
    }

    /**
     * Prepare query with values.
     * 
     * @param string $sql
     * @return string
     */
    protected function prepareQuery($sql)
    {
        $values = $this->getWhereValues();

        if (empty($values)) {
            return $sql;
        }

        return $this->db->prepare($sql, ...$values);
    }

    /**
     * Extract values from WHERE conditions for prepare().
     * 
     * @return array
     */
    protected function getWhereValues()
    {
        $values = [];

        foreach ($this->wheres as $where) {
            switch ($where['type']) {
                case 'basic':
                    $values[] = $where['value'];
                    break;

                case 'between':
                    $values[] = $where['start'];
                    $values[] = $where['end'];
                    break;

                case 'in':
                    $values = array_merge($values, $where['values']);
                    break;

                case 'like':
                    $values[] = $where['pattern'];
                    break;

                case 'or_group':
                    foreach ($where['conditions'] as $subWhere) {
                        if ($subWhere['type'] === 'basic') {
                            $values[] = $subWhere['value'];
                        } elseif ($subWhere['type'] === 'like') {
                            $values[] = $subWhere['pattern'];
                        }
                    }
                    break;
            }
        }

        return $values;
    }
}
