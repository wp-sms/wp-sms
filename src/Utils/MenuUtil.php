<?php

namespace WP_SMS\Utils;

use WP_SMS\Admin\Dashboard;

if (!defined('ABSPATH')) exit;

class MenuUtil
{
    private static $parentSlug = 'wsms';
    /**
     * List of Admin Page Slugs
     *
     * @var array
     */
    public static $pages = [
        'wsms' => 'wsms',
    ];

    /**
     * Initialize the menu registration
     */
    public static function init()
    {
        add_action('admin_menu', [__CLASS__, 'registerMenus'], 20);
    }

    /**
     * Register Menus in the WordPress Admin Panel
     */
    public static function registerMenus()
    {
        // Register the single top-level "WSMS" menu pointing to the React dashboard
        $icon = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9Ii01IDAgMzYgMzYiPjxwYXRoIGQ9Ik0wIDkuNTM3NTJWMTcuNzMzNUwxOC4yMTAxIDguMTc3NjRWMEwwIDkuNTM3NTJaIiBmaWxsPSIjYTdhYWFkIi8+PHBhdGggZD0iTTAgMjAuNzI5VjI4LjkwNjdMMjYgMTUuMjcxMVY3LjA5MzUxTDAgMjAuNzI5WiIgZmlsbD0iI2E3YWFhZCIvPjxwYXRoIGQ9Ik0yNS45OTcyIDE4LjI2NjZWMjYuMzUyNEw3LjgwNzM0IDM2LjAwMDFMNy43ODcxMSAyNy43MzA2TDI1Ljk5NzIgMTguMjY2NloiIGZpbGw9IiNhN2FhYWQiLz48L3N2Zz4=';
        add_menu_page('WSMS', 'WSMS', 'wpsms_sendsms', 'wsms', [Dashboard::instance(), 'view'], $icon);

        // Remove the auto-generated submenu item that WordPress creates matching the parent
        remove_submenu_page('wsms', 'wsms');

        // Still fire the filter so add-ons can hook into it for data purposes
        $list = [];
        $list = apply_filters('wp_sms_admin_menu_list', $list);
    }

    /**
     * Admin Page Slug
     *
     * @var string
     */
    public static $adminMenuSlug = 'wsms-[slug]';

    /**
     * Admin Page Load Action Slug
     *
     * @var string
     */
    public static $loadAdminSlug = 'toplevel_page_[slug]';

    /**
     * Get a List of Admin Pages with Slugs
     *
     * @return array
     */
    public static function getAdminPageList()
    {
        $adminList = [];
        foreach (self::$pages as $pageKey => $pageSlug) {
            $adminList[$pageKey] = self::getPageSlug($pageSlug);
        }

        return apply_filters('wp_sms_admin_page_list', $adminList);
    }

    /**
     * Check if the current page is a WP SMS admin page.
     *
     * @param string $page
     * @return bool
     */
    public static function isInPage($page)
    {
        global $pagenow;
        return is_admin() && $pagenow === 'admin.php' && isset($_REQUEST['page']) && $_REQUEST['page'] === self::getPageSlug($page);
    }

    /**
     * Check if User is in a WP SMS Plugin Page
     *
     * @return bool
     */
    public static function isInPluginPage()
    {
        global $pagenow;

        if (is_admin() && $pagenow === 'admin.php' && isset($_REQUEST['page'])) {
            $page = sanitize_text_field($_REQUEST['page']);

            if ($page === self::$parentSlug) {
                return true;
            }

            // Check for subpages
            $pageName = self::getPageKeyFromSlug($page);
            return is_array($pageName) && count($pageName) > 0;
        }
        return false;
    }

    /**
     * Convert a Page Slug to its Page Key
     *
     * @param string $pageSlug
     * @return mixed
     */
    public static function getPageKeyFromSlug($pageSlug)
    {
        // If it's a top-level menu (exactly 'wsms'), then return it directly
        if ($pageSlug === self::$parentSlug) {
            return [$pageSlug];
        }

        // If it starts with "wsms-" then remove that prefix and return the rest
        if (str_starts_with($pageSlug, self::$parentSlug . '-')) {
            $key = substr($pageSlug, strlen(self::$parentSlug . '-'));
            return [$key];
        }

        // Otherwise, it's already a short slug (e.g. 'add-ons')
        return [$pageSlug];
    }

    /**
     * Generate Admin URL
     *
     * @param string|null $page
     * @param array $args
     * @return string
     */
    public static function getAdminUrl($page = null, $args = [])
    {
        if (array_key_exists($page, self::getAdminPageList())) {
            $page = self::getPageSlug($page);
        }

        return add_query_arg(array_merge(['page' => $page], $args), admin_url('admin.php'));
    }

    /**
     * Get Menu List
     *
     * @return array
     */
    public static function getMenuList()
    {
        $list = [];
        $list = apply_filters('wp_sms_admin_menu_list', $list);

        uasort($list, function ($a, $b) {
            return ($a['priority'] ?? 999) <=> ($b['priority'] ?? 999);
        });

        return $list;
    }

    /**
     * Get Page Slug
     *
     * @param string $pageSlug
     * @return string
     */
    public static function getPageSlug($pageSlug)
    {
        if ($pageSlug === self::$parentSlug) {
            return $pageSlug;
        }

        return str_ireplace('[slug]', $pageSlug, self::$adminMenuSlug);
    }

    /**
     * Get Action Menu Slug
     *
     * @param string $pageSlug
     * @return string
     */
    public static function getActionMenuSlug($pageSlug)
    {
        return str_ireplace('[slug]', self::getPageSlug($pageSlug), self::$loadAdminSlug);
    }

    /**
     * Get the Current Admin Page
     *
     * @return mixed
     */
    public static function getCurrentPage()
    {
        $currentPage = Request::get('page');
        $pagesList   = self::getMenuList();

        if (!$currentPage) {
            return false;
        }

        $currentPage = self::getPageKeyFromSlug($currentPage);
        $currentPage = reset($currentPage);

        $filteredPages = array_filter($pagesList, function ($page) use ($currentPage) {
            return $page['page_url'] === $currentPage;
        });

        return reset($filteredPages);
    }
}
