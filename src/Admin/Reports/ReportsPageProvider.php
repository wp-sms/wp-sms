<?php

namespace WP_SMS\Admin\Reports;

/**
 * ReportsPageProvider - Registry for all report pages in the system.
 * 
 * This singleton manages registration and retrieval of report pages.
 */
class ReportsPageProvider
{
    /**
     * Singleton instance.
     * 
     * @var ReportsPageProvider|null
     */
    private static $instance = null;

    /**
     * Registered report pages.
     * 
     * @var array
     */
    private $pages = [];

    /**
     * Private constructor for singleton.
     */
    private function __construct()
    {
    }

    /**
     * Get singleton instance.
     * 
     * @return ReportsPageProvider
     */
    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Register a report page.
     * 
     * @param string $slug Unique identifier for the report page
     * @param mixed $pageInstance Instance of AbstractReportPage
     * @return void
     */
    public function register($slug, $pageInstance)
    {
        $this->pages[$slug] = $pageInstance;
    }

    /**
     * Get a specific report page by slug.
     * 
     * @param string $slug
     * @return mixed|null
     */
    public function getPage($slug)
    {
        return isset($this->pages[$slug]) ? $this->pages[$slug] : null;
    }

    /**
     * Get all registered report pages.
     * 
     * @return array
     */
    public function getAllPages()
    {
        return $this->pages;
    }

    /**
     * Check if a report page exists.
     * 
     * @param string $slug
     * @return bool
     */
    public function hasPage($slug)
    {
        return isset($this->pages[$slug]);
    }

    /**
     * Get list of all page slugs with metadata.
     * 
     * @return array
     */
    public function getPagesList()
    {
        $list = [];

        foreach ($this->pages as $slug => $page) {
            $list[] = [
                'slug' => $slug,
                'label' => $page->getLabel(),
                'description' => $page->getDescription(),
            ];
        }

        return $list;
    }
}

