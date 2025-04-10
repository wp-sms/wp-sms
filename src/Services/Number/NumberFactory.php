<?php

namespace WP_SMS\Services\Number;

use RuntimeException;
use WP_SMS\Decorators\NumberDataDecorator;
use WP_SMS\Utils\Query;

class NumberFactory
{
    /**
     * @var string
     */
    private static $table = 'sms_numbers';

    /**
     * Insert a new number into the database.
     *
     * @param array $data
     * @return int
     * @throws RuntimeException
     */
    public static function insertNumber(array $data)
    {
        global $wpdb;

        $query = Query::insert(self::$table)
            ->values($data);

        $result = $query->execute();

        if (!$result) {
            throw new RuntimeException("Failed to insert number into the database.");
        }

        return $wpdb->insert_id;
    }

    /**
     * Get a number by its ID.
     *
     * @param int $id
     * @return object|null
     */
    public static function getNumberById($id)
    {
        $results = Query::select()
            ->from(self::$table)
            ->where('id', '=', $id)
            ->setDecorator(NumberDataDecorator::class)
            ->getAll();

        return !empty($results) ? $results[0] : null;
    }

    /**
     * Update a number by its ID.
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public static function updateNumber($id, array $data)
    {
        return (bool)Query::update(self::$table)
            ->set($data)
            ->where('id', '=', $id)
            ->execute();
    }

    /**
     * Get numbers by user ID.
     *
     * @param int $userId
     * @return array
     */
    public static function getNumbersByUserId($userId)
    {
        return Query::select()
            ->from(self::$table)
            ->where('user_id', '=', $userId)
            ->setDecorator(NumberDataDecorator::class)
            ->getAll();
    }

    /**
     * Delete a number by its ID.
     *
     * @param int $id
     * @return bool
     */
    public static function deleteNumber($id)
    {
        return (bool)Query::delete(self::$table)
            ->where('id', '=', $id)
            ->execute();
    }

    /**
     * Check if a number exists in the database.
     *
     * @param string $number
     * @return bool
     */
    public static function checkIfNumberExists($number)
    {
        $results = Query::select()
            ->from(self::$table)
            ->where('number', '=', $number)
            ->getAll();

        return !empty($results);
    }

    /**
     * Count the total numbers in the database.
     *
     * @return int
     */
    public static function countNumbers()
    {
        $results = Query::select('COUNT(*) as total')
            ->from(self::$table)
            ->getAll();

        return !empty($results) ? (int)$results[0]->total : 0;
    }

    /**
     * Paginate numbers.
     *
     * @param int $page
     * @param int $perPage
     * @param array $filters
     * @return array
     */
    public static function paginateNumbers($page, $perPage, array $filters = array())
    {
        $offset = ($page - 1) * $perPage;

        $query = Query::select()
            ->from(self::$table)
            ->setDecorator(NumberDataDecorator::class)
            ->limit($perPage, $offset);

        foreach ($filters as $field => $value) {
            $query->where($field, '=', $value);
        }

        return $query->getAll();
    }

    /**
     * Search numbers by a keyword in specified columns.
     *
     * @param string $keyword
     * @param array $columns
     * @return array
     */
    public static function searchNumbers($keyword, array $columns)
    {
        $query = Query::select()
            ->setDecorator(NumberDataDecorator::class)
            ->from(self::$table);

        foreach ($columns as $column) {
            $query->where($column, 'LIKE', "%$keyword%", 'OR');
        }

        return $query->getAll();
    }

    /**
     * Bulk insert numbers into the database.
     *
     * @param array $numbers
     * @return int
     * @throws RuntimeException
     */
    public static function bulkInsertNumbers(array $numbers)
    {
        global $wpdb;

        $insertedRows = 0;

        foreach ($numbers as $number) {
            $result = Query::insert(self::$table)
                ->values($number)
                ->execute();

            if ($result) {
                $insertedRows++;
            }
        }

        if ($insertedRows === 0) {
            throw new RuntimeException("No rows were inserted during bulk insert.");
        }

        return $insertedRows;
    }

    /**
     * Check if a number belongs to a specific user.
     *
     * @param string $number
     * @param int $userId
     * @return bool
     */
    public static function doesNumberBelongToUser($number, $userId)
    {
        $results = Query::select()
            ->from(self::$table)
            ->where('number', '=', $number)
            ->where('user_id', '=', $userId)
            ->getAll();

        return !empty($results);
    }

    /**
     * Get numbers with custom filters, ordering, and pagination.
     *
     * @param array $filters
     * @param string $orderBy
     * @param string $orderDirection
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function getNumbersWithFilters(
        array $filters,
              $orderBy = 'id',
              $orderDirection = 'ASC',
              $limit = 10,
              $offset = 0
    )
    {
        $query = Query::select()
            ->from(self::$table)
            ->orderBy($orderBy, $orderDirection)
            ->setDecorator(NumberDataDecorator::class)
            ->limit($limit, $offset);

        foreach ($filters as $field => $value) {
            $query->where($field, '=', $value);
        }

        return $query->getAll();
    }

    /**
     * Get numbers by their IDs.
     *
     * @param array $ids
     * @return array
     */
    public static function getNumbersByIds(array $ids)
    {
        return Query::select()
            ->from(self::$table)
            ->where('id', 'IN', $ids)
            ->setDecorator(NumberDataDecorator::class)
            ->getAll();
    }
}