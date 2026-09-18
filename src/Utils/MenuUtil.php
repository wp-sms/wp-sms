<?php

namespace WP_SMS\Utils;

use WP_SMS\Admin\Dashboard;

if (!defined('ABSPATH')) exit;

class MenuUtil
{
    private static $parentSlug = 'wsms';

    /**
     * Tab opened when admin.php?page=wsms has no tab, or an unknown one.
     */
    const DEFAULT_TAB = 'send-sms';

    /**
     * Capabilities that each open at least one page of the WSMS admin.
     *
     * A user holding any one of these gets the WSMS menu; the pages inside
     * it are then filtered per capability (see getSubmenus()).
     *
     * @var string[]
     */
    public static $capabilities = [
        'wpsms_sendsms',
        'wpsms_outbox',
        'wpsms_inbox',
        'wpsms_subscribers',
        'wpsms_setting',
    ];

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
        add_filter('submenu_file', [__CLASS__, 'highlightCurrentSubmenu'], 10, 2);
    }

    /**
     * Register Menus in the WordPress Admin Panel
     */
    public static function registerMenus()
    {
        // Register the single top-level "WSMS" menu pointing to the React dashboard
        $icon = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9Ii01IDAgMzYgMzYiPjxwYXRoIGQ9Ik0wIDkuNTM3NTJWMTcuNzMzNUwxOC4yMTAxIDguMTc3NjRWMEwwIDkuNTM3NTJaIiBmaWxsPSIjYTdhYWFkIi8+PHBhdGggZD0iTTAgMjAuNzI5VjI4LjkwNjdMMjYgMTUuMjcxMVY3LjA5MzUxTDAgMjAuNzI5WiIgZmlsbD0iI2E3YWFhZCIvPjxwYXRoIGQ9Ik0yNS45OTcyIDE4LjI2NjZWMjYuMzUyNEw3LjgwNzM0IDM2LjAwMDFMNy43ODcxMSAyNy43MzA2TDI1Ljk5NzIgMTguMjY2NloiIGZpbGw9IiNhN2FhYWQiLz48L3N2Zz4=';
        add_menu_page('WSMS', 'WSMS', self::getMenuCapability(), self::$parentSlug, [Dashboard::instance(), 'view'], $icon);

        // One real submenu entry per section of the React app, each guarded by its own
        // capability, so role editors and white-label tools can show or hide them one by one.
        foreach (self::getSubmenus() as $submenu) {
            add_submenu_page(self::$parentSlug, $submenu['title'], $submenu['title'], $submenu['capability'], self::getTabUrl($submenu['tab']));
        }

        // Remove the auto-generated submenu item that WordPress creates matching the parent
        remove_submenu_page(self::$parentSlug, self::$parentSlug);

        // Still fire the filter so add-ons can hook into it for data purposes
        $list = [];
        $list = apply_filters('wp_sms_admin_menu_list', $list);
    }

    /**
     * Capability for the top-level WSMS entry: the first plugin capability the
     * current user holds, so a user limited to, say, the inbox still gets the menu.
     *
     * @return string
     */
    public static function getMenuCapability()
    {
        foreach (self::$capabilities as $capability) {
            if (current_user_can($capability)) {
                return $capability;
            }
        }

        return self::$capabilities[0];
    }

    /**
     * Submenu entries of the WSMS menu.
     *
     * Each entry opens one tab of the React app and lists the other tabs it
     * covers, so the WordPress sidebar can keep it highlighted while the user
     * moves inside that section. Add-ons extend or trim the list through the
     * `wp_sms_admin_submenus` filter.
     *
     * @return array<string, array{title: string, capability: string, tab: string, tabs: string[]}>
     */
    public static function getSubmenus()
    {
        $submenus = [
            'send-sms'    => [
                'title'      => __('Send SMS', 'wp-sms'),
                'capability' => 'wpsms_sendsms',
                'tab'        => 'send-sms',
                'tabs'       => [],
            ],
            'outbox'      => [
                'title'      => __('Outbox', 'wp-sms'),
                'capability' => 'wpsms_outbox',
                'tab'        => 'outbox',
                'tabs'       => [],
            ],
            'subscribers' => [
                'title'      => __('Subscribers', 'wp-sms'),
                'capability' => 'wpsms_subscribers',
                'tab'        => 'subscribers',
                'tabs'       => [],
            ],
            'groups'      => [
                'title'      => __('Groups', 'wp-sms'),
                'capability' => 'wpsms_subscribers',
                'tab'        => 'groups',
                'tabs'       => [],
            ],
            'settings'    => [
                'title'      => __('Settings', 'wp-sms'),
                'capability' => 'wpsms_setting',
                'tab'        => 'overview',
                'tabs'       => [
                    'gateway',
                    'phone',
                    'message-button',
                    'notifications',
                    'authentication',
                    'newsletter',
                    'integrations',
                    'advanced',
                    'privacy',
                    'add-ons',
                    'sms-campaigns',
                    'cart-abandonment',
                    'woocommerce-pro',
                    'two-way-commands',
                    'two-way-settings',
                ],
            ],
        ];

        if (self::isAddonActive('wp-sms-pro/wp-sms-pro.php')) {
            $submenus = self::insertAfter($submenus, 'outbox', 'scheduled', [
                'title'      => __('Scheduled', 'wp-sms'),
                'capability' => 'wpsms_sendsms',
                'tab'        => 'scheduled',
                'tabs'       => [],
            ]);
        }

        if (self::isAddonActive('wp-sms-two-way/wp-sms-two-way.php')) {
            $submenus = self::insertAfter($submenus, 'groups', 'two-way-inbox', [
                'title'      => __('Inbox', 'wp-sms'),
                'capability' => 'wpsms_inbox',
                'tab'        => 'two-way-inbox',
                'tabs'       => [],
            ]);
        }

        return apply_filters('wp_sms_admin_submenus', $submenus);
    }

    /**
     * Map every known tab to the submenu URL that should be highlighted for it.
     *
     * @return array<string, string>
     */
    public static function getTabSubmenuMap()
    {
        $map = [];

        foreach (self::getSubmenus() as $submenu) {
            $url = self::getTabUrl($submenu['tab']);

            $map[$submenu['tab']] = $url;
            foreach ($submenu['tabs'] as $tab) {
                $map[$tab] = $url;
            }
        }

        return $map;
    }

    /**
     * Keep the matching submenu entry highlighted while inside the React app.
     *
     * @param string|null $submenuFile
     * @param string $parentFile
     * @return string|null
     */
    public static function highlightCurrentSubmenu($submenuFile, $parentFile)
    {
        if ($parentFile !== self::$parentSlug) {
            return $submenuFile;
        }

        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : self::DEFAULT_TAB;
        $map = self::getTabSubmenuMap();

        return isset($map[$tab]) ? $map[$tab] : $submenuFile;
    }

    /**
     * Relative admin URL of a tab, in the form WordPress stores submenu links.
     *
     * @param string $tab
     * @return string
     */
    public static function getTabUrl($tab)
    {
        return 'admin.php?page=' . self::$parentSlug . '&tab=' . $tab;
    }

    /**
     * @param string $plugin Plugin basename.
     * @return bool
     */
    private static function isAddonActive($plugin)
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active($plugin);
    }

    /**
     * Insert an entry into an associative array right after a given key.
     *
     * @param array $items
     * @param string $afterKey
     * @param string $key
     * @param mixed $value
     * @return array
     */
    private static function insertAfter(array $items, $afterKey, $key, $value)
    {
        $result = [];

        foreach ($items as $itemKey => $item) {
            $result[$itemKey] = $item;
            if ($itemKey === $afterKey) {
                $result[$key] = $value;
            }
        }

        if (!isset($result[$key])) {
            $result[$key] = $value;
        }

        return $result;
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
