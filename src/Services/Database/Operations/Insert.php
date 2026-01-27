<?php

namespace WP_SMS\Services\Database\Operations;

use RuntimeException;

/**
 * Handles data insertion and migration between database tables.
 *
 * This class provides methods to:
 * - Migrate data from a source table to a target table.
 * - Directly insert data into a target table.
 * - Update existing records based on `conditions` (e.g., ID).
 *
 * @package   Database
 * @version   1.0.0
 * @since     7.1
 * @author    Hooman
 */
class Insert extends AbstractTableOperation
{
    /**
     * The source table for data migration.
     *
     * @var string
     */
    protected $sourceTable;

    /**
     * The source table name (with WordPress prefix).
     *
     * @var string
     */
    protected $prefixedSourceTable;

    /**
     * Set the source table (old table) for the operation.
     *
     * @param string $sourceTable The source table name.
     * @return $this
     */
    public function setSourceTable(string $sourceTable)
    {
        $this->sourceTable = $sourceTable;
        $this->prefixedSourceTable = $this->wpdb->prefix . 'sms_' . $sourceTable;

        return $this;
    }

    /**
     * Execute the insert operation, supporting migration, inserts, and updates.
     *
     * @return void
     * @throws RuntimeException If operation fails.
     */
    public function execute()
    {
        try {
            $this->ensureConnection();
            $this->validateTableName();
            $this->validateArgs();
            $this->setFullTableName();

            if (!empty($this->sourceTable)) {
                $this->transactionHandler->executeInTransaction([$this, 'migrateData']);
            } elseif (!empty($this->args['conditions'])) {
                $this->transactionHandler->executeInTransaction([$this, 'insertOrUpdateData']);
            } else {
                $this->transactionHandler->executeInTransaction([$this, 'insertData']);
            }
        } catch (\Exception $e) {
            update_option('wp_sms_migration_status_detail', [
                'status'  => 'failed',
                'message' => $e->getMessage()
            ]);

            throw new RuntimeException(
                sprintf("Failed to insert/update data in table `%s`: %s", $this->tableName, $e->getMessage())
            );
        }
    }

    /**
     * Handles inserting or updating data based on `conditions` (e.g., ID).
     *
     * @return void
     * @throws RuntimeException If operation fails.
     */
    public function insertOrUpdateData()
    {
        $mapping = $this->args['mapping'] ?? [];
        $conditions = $this->args['conditions'] ?? [];

        if (empty($mapping) || empty($conditions)) {
            throw new RuntimeException("Mapping and conditions are required for updating data.");
        }

        // Construct WHERE clause from conditions
        $whereClauses = [];
        $params = [];

        foreach ($conditions as $column => $value) {
            $whereClauses[] = "`$column` = %s";
            $params[] = $value;
        }

        $whereQuery = implode(' AND ', $whereClauses);

        // Check if a matching record exists
        $existsQuery = "SELECT COUNT(*) FROM {$this->fullName} WHERE $whereQuery";
        $exists = $this->wpdb->get_var($this->wpdb->prepare($existsQuery, ...$params));

        if ($exists > 0) {
            // Update existing record
            $result = $this->wpdb->update($this->fullName, $mapping, $conditions);
            if ($result === false) {
                throw new RuntimeException("Failed to update data: {$this->wpdb->last_error}");
            }
        } else {
            // Insert new record
            $mergedData = array_merge($mapping, $conditions);
            $result = $this->wpdb->insert($this->fullName, $mergedData);
            if ($result === false) {
                throw new RuntimeException("Failed to insert data: {$this->wpdb->last_error}");
            }
        }
    }

    /**
     * Insert data directly into the table.
     *
     * @return void
     * @throws RuntimeException If insert fails.
     */
    public function insertData()
    {
        $data = $this->args['data'] ?? $this->args['mapping'] ?? [];

        if (empty($data)) {
            throw new RuntimeException("Data is required for insert operation.");
        }

        $result = $this->wpdb->insert($this->fullName, $data);

        if ($result === false) {
            throw new RuntimeException("Failed to insert data: {$this->wpdb->last_error}");
        }
    }

    /**
     * Migrate data operation to be executed within a transaction.
     *
     * @return void
     * @throws RuntimeException If migration fails.
     */
    public function migrateData()
    {
        if (empty($this->sourceTable)) {
            throw new RuntimeException("Source table is not specified for migration.");
        }

        $mapping = $this->args['mapping'] ?? [];
        $distinctFields = $this->args['distinct_fields'] ?? [];

        if (empty($mapping)) {
            throw new RuntimeException("Mapping is required for migration.");
        }

        $batchSize = $this->args['batch_size'] ?? 50;
        $offset = $this->args['offset'] ?? 0;

        // Prepare the columns for fetching data from the source table
        $sourceColumns = implode(', ', array_values($mapping));

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT $sourceColumns FROM {$this->prefixedSourceTable} LIMIT %d OFFSET %d",
                $batchSize,
                $offset
            ),
            ARRAY_A
        );

        if ($rows === null || $rows === false) {
            throw new RuntimeException("Failed to fetch rows: {$this->wpdb->last_error}");
        }

        foreach ($rows as $row) {
            $mappedRow = [];
            foreach ($mapping as $targetColumn => $sourceColumn) {
                if (isset($row[$sourceColumn])) {
                    $mappedRow[$targetColumn] = $row[$sourceColumn];
                }
            }

            if ($this->shouldSkipDuplicate($mappedRow, $distinctFields)) {
                continue;
            }

            $this->performInsert($mappedRow);
        }
    }

    /**
     * Determines whether a row should be skipped based on `distinct_fields`.
     *
     * @param array $row The row data to check.
     * @param array $distinctFields Fields to check for duplicates.
     * @return bool True if duplicate should be skipped.
     */
    private function shouldSkipDuplicate(array $row, array $distinctFields): bool
    {
        if (empty($distinctFields)) {
            return false;
        }

        $conditions = [];
        foreach ($distinctFields as $field) {
            if (isset($row[$field])) {
                $conditions[] = $this->wpdb->prepare("`$field` = %s", $row[$field]);
            }
        }

        if (empty($conditions)) {
            return false;
        }

        $conditionQuery = implode(' AND ', $conditions);
        $exists = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->fullName} WHERE $conditionQuery");

        return $exists > 0;
    }

    /**
     * Perform the insert operation for a single row.
     *
     * @param array $row The row data to insert.
     * @return void
     * @throws RuntimeException If insert fails.
     */
    private function performInsert(array $row)
    {
        $result = $this->wpdb->insert($this->fullName, $row);

        if ($result === false) {
            throw new RuntimeException("Failed to insert row: {$this->wpdb->last_error}");
        }
    }
}
