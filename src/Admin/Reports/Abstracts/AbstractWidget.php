<?php

namespace WP_SMS\Admin\Reports\Abstracts;

/**
 * AbstractWidget - Base class for report widgets.
 * 
 * Each widget is responsible for fetching and formatting its own data.
 */
abstract class AbstractWidget
{
    /**
     * Get widget type.
     * 
     * @return string One of: 'kpi', 'chart', 'table', 'funnel', 'map'
     */
    abstract public function getType();

    /**
     * Get widget label/title.
     * 
     * @return string
     */
    abstract public function getLabel();

    /**
     * Get widget data based on filters.
     * 
     * Returns chart-ready data formatted for frontend consumption.
     * 
     * @param array $filters Applied global filters
     * @return array
     */
    abstract public function getData($filters);

    /**
     * Get widget configuration/options.
     * 
     * @return array
     */
    public function getConfig()
    {
        return [];
    }
}

