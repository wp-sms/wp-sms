<?php

namespace WP_SMS\Utils;

use WP_SMS\User\UserHelper;

class MenuUtil
{
    /**
     * List of Admin Page Slugs
     *
     * @var array
     */
    public static $pages = [
        'overview'           => 'overview',
        'exclusions'         => 'exclusions',
        'referrals'          => 'referrals',
        'optimization'       => 'optimization',
        'settings'           => 'settings',
        'plugins'            => 'plugins',
        'author-analytics'   => 'author-analytics',
        'privacy-audit'      => 'privacy-audit',
        'geographic'         => 'geographic',
        'content-analytics'  => 'content-analytics',
        'devices'            => 'devices',
        'category-analytics' => 'category-analytics',
        'pages'              => 'pages',
        'visitors'           => 'visitors',
    ];

    /**
     * Admin Page Slug Template
     *
     * @var string
     */

    /**
     * Initialize the menu registration
     */
    public static function init()
    {
        add_action('admin_menu', [__CLASS__, 'registerMenus']);
    }

    /**
     * Register Menus in the WordPress Admin Panel
     */
    public static function registerMenus()
    {
        // Get the menu list
        $menuList = self::getMenuList();

        foreach ($menuList as $key => $menu) {
            $capability = $menu['cap'] ?? 'manage_options';
            $callback   = isset($menu['method']) ? [$menu['method']] : '__return_null';
            $menuTitle  = $menu['title'] ?? $key;

            // Check if it's a submenu or a main menu
            if (isset($menu['sub'])) {
                add_submenu_page(
                    self::getPageSlug($menu['sub']), // Parent slug
                    $menu['title'],                 // Page title
                    $menuTitle,                     // Menu title
                    $capability,                    // Capability
                    self::getPageSlug($menu['page_url']), // Slug
                    $callback                       // Callback
                );
            } else {
                add_menu_page(
                    $menu['title'],                // Page title
                    $menuTitle,                    // Menu title
                    $capability,                   // Capability
                    self::getPageSlug($menu['page_url']), // Slug
                    $callback,                     // Callback
                    $menu['icon'] ?? '',           // Icon (optional)
                    $menu['priority'] ?? null      // Position (optional)
                );
            }
        }
    }


    public static $admin_menu_slug = 'wps_[slug]_page';

    /**
     * Admin Page Load Action Slug
     *
     * @var string
     */
    public static $load_admin_slug = 'toplevel_page_[slug]';

    /**
     * Admin Page Load Submenu Action Slug
     *
     * @var string
     */
    public static $load_admin_submenu_slug = 'sms_page_[slug]';

    /**
     * Get a List of Admin Pages with Slugs
     *
     * @return array
     */
    public static function getAdminPageList(): array
    {
        $adminList = [];
        foreach (self::$pages as $pageKey => $pageSlug) {
            $adminList[$pageKey] = self::getPageSlug($pageSlug);
        }

        return apply_filters('wp_sms_admin_page_list', $adminList);
    }

    /**
     * Check if the current page is a WP Statistics admin page.
     *
     * @param string $page
     * @return bool
     */
    public static function isInPage(string $page): bool
    {
        global $pagenow;
        return is_admin() && $pagenow === 'admin.php' && isset($_REQUEST['page']) && $_REQUEST['page'] === self::getPageSlug($page);
    }

    /**
     * Check if User is in a WP Statistics Plugin Page
     *
     * @return bool
     */
    public static function isInPluginPage(): bool
    {
        global $pagenow;
        if (is_admin() && $pagenow === 'admin.php' && isset($_REQUEST['page'])) {
            $pageName = self::getPageKeyFromSlug(sanitize_text_field($_REQUEST['page']));
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
    public static function getPageKeyFromSlug(string $pageSlug)
    {
        $menuSlugParts = explode('[slug]', self::$admin_menu_slug);
        preg_match('/(?<=' . $menuSlugParts[0] . ').*?(?=' . $menuSlugParts[1] . ')/', $pageSlug, $pageName);
        return $pageName; // Use $pageName[0] to access the key
    }

    /**
     * Generate Admin URL
     *
     * @param string|null $page
     * @param array $args
     * @return string
     */
    public static function getAdminUrl(?string $page = null, array $args = []): string
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
    public static function getMenuList(): array
    {
        $manageCap = UserHelper::validateCapability(OptionUtil::get('manage_capability', 'manage_options'));

        $list = [
            'settings' => [
                'sub'      => 'overview',
                'title'    => __('Settings', 'wp-statistics'),
                'cap'      => $manageCap,
                'page_url' => 'settings',
                'method'   => 'settings',
                'priority' => 100,
            ],
            'optimize' => [
                'sub'      => 'overview',
                'title'    => __('Optimization', 'wp-statistics'),
                'cap'      => $manageCap,
                'page_url' => 'optimization',
                'method'   => 'optimization',
                'priority' => 110,
            ],
        ];

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
    public static function getPageSlug(string $pageSlug): string
    {
        return str_ireplace('[slug]', $pageSlug, self::$admin_menu_slug);
    }

    /**
     * Get Action Menu Slug
     *
     * @param string $pageSlug
     * @return string
     */
    public static function getActionMenuSlug(string $pageSlug): string
    {
        return str_ireplace('[slug]', self::getPageSlug($pageSlug), self::$load_admin_slug);
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
