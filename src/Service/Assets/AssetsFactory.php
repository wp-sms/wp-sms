<?php

namespace WP_SMS\Service\Assets;

use WP_SMS\Service\Assets\Handlers\AdminHandler;
use WP_SMS\Service\Assets\Handlers\FrontendHandler;
use WP_SMS\Service\Assets\Handlers\DashboardHandler;

if (!defined('ABSPATH')) exit;

class AssetsFactory
{
    /**
     * @var AdminHandler|null
     */
    private static $admin;

    /**
     * @var FrontendHandler|null
     */
    private static $frontend;

    /**
     * @var DashboardHandler|null
     */
    private static $dashboard;

    /**
     * Create and return the admin asset handler.
     *
     * @return AdminHandler|null
     */
    public static function admin()
    {
        if (self::$admin === null) {
            self::$admin = new AdminHandler();
        }

        return self::$admin;
    }

    /**
     * Create and return the frontend asset handler.
     *
     * @return FrontendHandler|null
     */
    public static function frontend()
    {
        if (self::$frontend === null) {
            self::$frontend = new FrontendHandler();
        }

        return self::$frontend;
    }

    /**
     * Create and return the dashboard asset handler.
     *
     * @return DashboardHandler|null
     */
    public static function dashboard()
    {
        if (self::$dashboard === null) {
            self::$dashboard = new DashboardHandler();
        }

        return self::$dashboard;
    }
}
