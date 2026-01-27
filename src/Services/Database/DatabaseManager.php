<?php

namespace WP_SMS\Services\Database;

/**
 * Interface for database operations.
 *
 * Defines a contract for classes that perform database-related operations,
 * requiring an implementation of the `execute` method.
 *
 * @package   Database
 * @version   1.0.0
 * @since     7.1
 * @author    Hooman
 */
interface DatabaseManager
{
    /**
     * Execute the database operation.
     *
     * @return mixed The result of the operation, based on the implementation.
     */
    public function execute();
}
