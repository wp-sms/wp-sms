<?php

namespace WP_SMS\Admin\Logs\Abstracts;

/**
 * AbstractLogPage - Base class for all log pages.
 * 
 * Defines the contract that all log pages must implement.
 */
abstract class AbstractLogPage
{
    /**
     * Get unique slug for this log page.
     * 
     * @return string
     */
    abstract public function getSlug();

    /**
     * Get display label for this log page.
     * 
     * @return string
     */
    abstract public function getLabel();

    /**
     * Get brief description of this log page.
     * 
     * @return string
     */
    abstract public function getDescription();

    /**
     * Get column schema for the table.
     * 
     * Returns array of column definitions:
     * [
     *   ['key' => 'column_name', 'label' => 'Display Label', 'sortable' => true, 'default' => true],
     *   ...
     * ]
     * 
     * @return array
     */
    abstract public function getSchema();

    /**
     * Get available filters for this log page.
     * 
     * Returns array of filter definitions:
     * [
     *   [
     *     'key' => 'filter_name',
     *     'label' => 'Display Label',
     *     'type' => 'date-range|multi-select|text|number|radio|checkbox',
     *     'options' => [...],  // for select types
     *     'default' => mixed,
     *   ],
     *   ...
     * ]
     * 
     * @return array
     */
    abstract public function getFilters();

    /**
     * Get paginated, filtered, and sorted log data.
     * 
     * @param array $filters Applied filters
     * @param array $sorts Sorting configuration [['column' => 'timestamp_utc', 'direction' => 'DESC'], ...]
     * @param int $page Page number (1-based)
     * @param int $perPage Items per page
     * @return array ['rows' => [], 'totalCount' => 0, 'page' => 1, 'perPage' => 50, 'totalPages' => 1]
     */
    abstract public function getData($filters, $sorts, $page, $perPage);

    /**
     * Get single row details for side drawer.
     * 
     * @param int|string $id Row identifier
     * @return array|null
     */
    abstract public function getRow($id);

    /**
     * Get required capability to view this log.
     * 
     * @return string
     */
    public function getCapability()
    {
        return 'manage_options';
    }

    /**
     * Check if current user can view this log.
     * 
     * @return bool
     */
    public function canView()
    {
        return current_user_can($this->getCapability());
    }
}

