<?php

namespace WP_SMS\Admin\Reports\Abstracts;

/**
 * AbstractReportPage - Base class for all report pages.
 * 
 * Defines the contract that all report pages must implement.
 */
abstract class AbstractReportPage
{
    /**
     * Get unique slug for this report page.
     * 
     * @return string
     */
    abstract public function getSlug();

    /**
     * Get display label for this report page.
     * 
     * @return string
     */
    abstract public function getLabel();

    /**
     * Get brief description of this report page.
     * 
     * @return string
     */
    abstract public function getDescription();

    /**
     * Get global filters for this report page.
     * 
     * Returns array of filter definitions similar to log pages.
     * 
     * @return array
     */
    abstract public function getFilters();

    /**
     * Get widget definitions for this report page.
     * 
     * Returns array of widget configurations:
     * [
     *   [
     *     'id' => 'widget_identifier',
     *     'type' => 'kpi|chart|table|funnel|map',
     *     'label' => 'Widget Title',
     *     'widgetClass' => WidgetClassName::class,
     *     'layout' => ['row' => 1, 'col' => 1, 'span' => 6],
     *   ],
     *   ...
     * ]
     * 
     * @return array
     */
    abstract public function getWidgets();

    /**
     * Get data for all widgets based on filters.
     * 
     * @param array $filters Applied filters
     * @return array ['widget_id' => [...widget data...], ...]
     */
    abstract public function getWidgetData($filters);

    /**
     * Get required capability to view this report.
     * 
     * @return string
     */
    public function getCapability()
    {
        return 'manage_options';
    }

    /**
     * Check if current user can view this report.
     * 
     * @return bool
     */
    public function canView()
    {
        return current_user_can($this->getCapability());
    }
}

