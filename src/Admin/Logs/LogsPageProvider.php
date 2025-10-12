<?php

namespace WP_SMS\Admin\Logs;

/**
 * LogsPageProvider - Registry for all log pages in the system.
 * 
 * This singleton manages registration and retrieval of log pages.
 */
class LogsPageProvider
{
    /**
     * Singleton instance.
     * 
     * @var LogsPageProvider|null
     */
    private static $instance = null;

    /**
     * Registered log pages.
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
     * @return LogsPageProvider
     */
    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Register a log page.
     * 
     * @param string $slug Unique identifier for the log page
     * @param mixed $pageInstance Instance of AbstractLogPage
     * @return void
     */
    public function register($slug, $pageInstance)
    {
        $this->pages[$slug] = $pageInstance;
    }

    /**
     * Get a specific log page by slug.
     * 
     * @param string $slug
     * @return mixed|null
     */
    public function getPage($slug)
    {
        return isset($this->pages[$slug]) ? $this->pages[$slug] : null;
    }

    /**
     * Get all registered log pages.
     * 
     * @return array
     */
    public function getAllPages()
    {
        return $this->pages;
    }

    /**
     * Check if a log page exists.
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

