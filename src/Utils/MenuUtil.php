<?php

namespace WP_SMS\Utils;

use WP_SMS\User\UserHelper;

class MenuUtil
{
    private static $parentSlug = 'wp-sms';
    /**
     * List of Admin Page Slugs
     *
     * @var array
     */
    public static $pages = [
        'wp-sms'              => 'wp-sms',
        'outbox'              => 'outbox',
        'inbox'               => 'inbox',
        'subscribers'         => 'subscribers',
        'subscribers-group'   => 'subscribers-group',
        'subscribers-privacy' => 'subscribers-privacy',
        'settings'            => 'settings',
        'integrations'        => 'integrations',
        'add-ons'             => 'add-ons',
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
        add_action('admin_menu', [__CLASS__, 'registerMenus'], 20);
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
            $method = 'log';
            $name   = $menu['title'];

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
                //Check Conditions For Show Menu
                if (OptionUtil::checkOptionRequire($menu) === true) {
                    add_submenu_page(self::$parentSlug, $menu['title'], $name, $capability, self::getPageSlug($menu['page_url']), $callback);
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
     * Check if the current page is a WP SMS admin page.
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
     * Check if User is in a WP SMS Plugin Page
     *
     * @return bool
     */
    public static function isInPluginPage(): bool
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
    public static function getPageKeyFromSlug(string $pageSlug)
    {
        // If it's a top-level menu (exactly 'wp-sms'), then return it directly
        if ($pageSlug === self::$parentSlug) {
            return [$pageSlug];
        }

        // If it starts with "wp-sms-" then remove that prefix and return the rest
        if (str_starts_with($pageSlug, self::$parentSlug . '-')) {
            $key = substr($pageSlug, strlen(self::$parentSlug . '-'));
            return [$key];
        }

        // Otherwise, it’s already a short slug (e.g. 'add-ons')
        return [$pageSlug];
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
        if ($pageSlug === self::$parentSlug) {
            return $pageSlug;
        }

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
        $pagesList   = array_merge(self::getHardcodedMenuList(), self::getMenuList());

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

    /**
     *
     */
    public static function getHardcodedMenuList(): array
    {
        return [
            [
                'title'    => esc_html__('Send SMS', 'wp-sms'),
                'name'     => esc_html__('SMS', 'wp-sms'),
                'cap'      => 'wpsms_sendsms',
                'page_url' => 'wp-sms',
                'callback' => '',
                'icon'     => 'dashicons-email-alt',
                'priority' => 1,
            ],
            [
                'sub'      => 'wp-sms',
                'title'    => esc_html__('Outbox', 'wp-sms'),
                'name'     => esc_html__('Outbox', 'wp-sms'),
                'cap'      => 'wpsms_outbox',
                'page_url' => 'outbox',
                'callback' => '',
                'priority' => 2,
            ],
            [
                'sub'      => 'wp-sms',
                'title'    => esc_html__('Inbox', 'wp-sms'),
                'name'     => esc_html__('Inbox', 'wp-sms'),
                'cap'      => 'wpsms_inbox',
                'page_url' => 'inbox',
                'callback' => '',
                'priority' => 3,
            ],
            [
                'sub'      => 'wp-sms',
                'title'    => esc_html__('Subscribers', 'wp-sms'),
                'name'     => esc_html__('Subscribers', 'wp-sms'),
                'cap'      => 'wpsms_subscribers',
                'page_url' => 'subscribers',
                'callback' => '',
                'priority' => 4,
            ],
            [
                'sub'      => 'wp-sms',
                'title'    => esc_html__('Groups', 'wp-sms'),
                'name'     => esc_html__('Groups', 'wp-sms'),
                'cap'      => 'wpsms_subscribers',
                'page_url' => 'subscribers-group',
                'callback' => '',
                'priority' => 5,
            ],
            [
                'sub'      => 'wp-sms',
                'title'    => esc_html__('Privacy', 'wp-sms'),
                'name'     => esc_html__('Privacy', 'wp-sms'),
                'cap'      => 'wpsms_setting',
                'page_url' => 'subscribers-privacy',
                'callback' => '',
                'priority' => 6,
            ],
            [
                'sub'      => 'wp-sms',
                'title'    => esc_html__('Settings', 'wp-sms'),
                'name'     => esc_html__('Settings', 'wp-sms'),
                'cap'      => 'wpsms_setting',
                'page_url' => 'settings',
                'callback' => '',
                'priority' => 7,
            ],
            [
                'sub'      => 'wp-sms',
                'title'    => esc_html__('Integrations', 'wp-sms'),
                'name'     => esc_html__('Integrations', 'wp-sms'),
                'cap'      => 'wpsms_setting',
                'page_url' => 'integrations',
                'callback' => '',
                'priority' => 8,
            ],
        ];
    }
}
