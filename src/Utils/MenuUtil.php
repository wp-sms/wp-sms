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
        // Get the read/write capabilities.
        $capability = $menu['cap'] ?? 'manage_options';
        //Show Admin Menu List

        foreach (self::getMenuList() as $key => $menu) {

            //Check Default variable
            $method     = 'log';
            $name       = $menu['title'];

            if (array_key_exists('cap', $menu)) {
                $capability = $menu['cap'];
            }

            if (array_key_exists('method', $menu)) {
                $method = $menu['method'];
            }

            if (array_key_exists('name', $menu)) {
                $name = $menu['name'];
            }

            // Assume '\WP_SMS\\' is a constant base namespace for your classes.
            $baseNamespace = '\WP_SMS\\';

            // Determine the class name. Use $menu['callback'] if it's set; otherwise, construct the name from $method.
            $className = $menu['callback'] ?? $baseNamespace . $method . '_page';
            // Now, ensure that the 'view' method exists in the determined class.
            if (method_exists($className, 'view')) {
                $callback = [$className::instance(), 'view'];
            } else {
                continue;
            }

            //Check if SubMenu or Main Menu
            if (array_key_exists('sub', $menu)) {
                //Check if add Break Line
                if (array_key_exists('break', $menu)) {
                    add_submenu_page(self::getPageSlug($menu['sub']), '', '', $capability, 'wps_break_menu', $callback);
                }

                //Check Conditions For Show Menu
                if (OptionUtil::checkOptionRequire($menu) === true) {
                    add_submenu_page(self::getPageSlug($menu['sub']), $menu['title'], $name, $capability, self::getPageSlug($menu['page_url']), $callback);
                }
            } else {
                add_menu_page($menu['title'], $name, $capability, self::getPageSlug($menu['page_url']), $callback, $menu['icon']);
            }
        }
    }


    public static $admin_menu_slug = 'wp-sms-[slug]';

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
