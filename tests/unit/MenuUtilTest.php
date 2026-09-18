<?php

namespace unit;

use WP_SMS\Utils\MenuUtil;
use WP_UnitTestCase;

/**
 * The WSMS admin menu: one real submenu per section, each behind its own capability.
 */
class MenuUtilTest extends WP_UnitTestCase
{
    private $userId;

    public function setUp(): void
    {
        parent::setUp();

        remove_role('wsms_tester');
        add_role('wsms_tester', 'WSMS Tester', ['read' => true]);

        $this->userId = self::factory()->user->create(['role' => 'wsms_tester']);
        wp_set_current_user($this->userId);
    }

    public function tearDown(): void
    {
        global $menu, $submenu, $_wp_menu_nopriv, $_wp_submenu_nopriv;

        $menu = $submenu = $_wp_menu_nopriv = $_wp_submenu_nopriv = [];
        unset($_GET['tab']);
        remove_role('wsms_tester');

        parent::tearDown();
    }

    private function grant(...$capabilities)
    {
        $role = get_role('wsms_tester');
        foreach ($capabilities as $capability) {
            $role->add_cap($capability);
        }
        // Re-read the user so the new role capabilities apply
        wp_set_current_user(0);
        wp_set_current_user($this->userId);
    }

    private function registerMenus()
    {
        global $menu, $submenu, $_wp_menu_nopriv, $_wp_submenu_nopriv;

        $menu = $submenu = $_wp_menu_nopriv = $_wp_submenu_nopriv = [];
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        MenuUtil::registerMenus();
    }

    /**
     * Visible submenu titles in menu order.
     */
    private function visibleSubmenuTitles()
    {
        global $submenu;

        return array_map(function ($item) {
            return $item[0];
        }, isset($submenu['wsms']) ? array_values($submenu['wsms']) : []);
    }

    public function testEverySubmenuIsGuardedByAPluginCapability(): void
    {
        foreach (MenuUtil::getSubmenus() as $key => $submenu) {
            $this->assertContains($submenu['capability'], MenuUtil::$capabilities, "Submenu {$key} must use a wpsms_* capability");
            $this->assertNotEmpty($submenu['tab'], "Submenu {$key} must open a tab");
        }
    }

    public function testMenuCapabilityIsTheFirstOneTheUserHolds(): void
    {
        $this->assertSame('wpsms_sendsms', MenuUtil::getMenuCapability(), 'A user with no plugin capability falls back to the default');

        $this->grant('wpsms_inbox');
        $this->assertSame('wpsms_inbox', MenuUtil::getMenuCapability());

        $this->grant('wpsms_sendsms');
        $this->assertSame('wpsms_sendsms', MenuUtil::getMenuCapability());
    }

    public function testUserWithEveryCapabilitySeesEveryCoreSubmenu(): void
    {
        $this->grant(...MenuUtil::$capabilities);
        $this->registerMenus();

        $titles = $this->visibleSubmenuTitles();

        foreach (['Send SMS', 'Outbox', 'Subscribers', 'Groups', 'Settings'] as $title) {
            $this->assertContains($title, $titles);
        }
        $this->assertNotContains('WSMS', $titles, 'The auto-generated parent entry is removed');
    }

    public function testSubmenuLinksOpenTheirTabInTheDashboard(): void
    {
        global $submenu;

        $this->grant(...MenuUtil::$capabilities);
        $this->registerMenus();

        $links = array_column($submenu['wsms'], 2, 0);

        $this->assertSame('admin.php?page=wsms&tab=send-sms', $links['Send SMS']);
        $this->assertSame('admin.php?page=wsms&tab=overview', $links['Settings']);
    }

    public function testUserWithOnlySubscribersCapabilityGetsOnlyThoseEntries(): void
    {
        $this->grant('wpsms_subscribers');
        $this->registerMenus();

        $this->assertSame(['Subscribers', 'Groups'], $this->visibleSubmenuTitles());
    }

    public function testUserWithoutAnyPluginCapabilityGetsNoSubmenu(): void
    {
        global $menu, $_wp_submenu_nopriv;

        $this->registerMenus();

        $this->assertSame('wpsms_sendsms', $menu[0][1], 'The parent falls back to the default capability, which WordPress then denies');
        $this->assertSame([], $this->visibleSubmenuTitles());
        $this->assertCount(count(MenuUtil::getSubmenus()), $_wp_submenu_nopriv['wsms']);
    }

    public function testCurrentSubmenuFollowsTheOpenTab(): void
    {
        $this->assertSame('admin.php?page=wsms&tab=send-sms', MenuUtil::highlightCurrentSubmenu(null, 'wsms'), 'No tab means Send SMS');

        $_GET['tab'] = 'gateway';
        $this->assertSame('admin.php?page=wsms&tab=overview', MenuUtil::highlightCurrentSubmenu(null, 'wsms'), 'Settings pages highlight the Settings entry');

        $_GET['tab'] = 'no-such-tab';
        $this->assertNull(MenuUtil::highlightCurrentSubmenu(null, 'wsms'));

        $_GET['tab'] = 'outbox';
        $this->assertSame('edit.php', MenuUtil::highlightCurrentSubmenu('edit.php', 'edit.php'), 'Other menus are left alone');
    }

    public function testTabMapCoversEveryTabASubmenuDeclares(): void
    {
        $map = MenuUtil::getTabSubmenuMap();

        foreach (MenuUtil::getSubmenus() as $submenu) {
            $this->assertSame(MenuUtil::getTabUrl($submenu['tab']), $map[$submenu['tab']]);
            foreach ($submenu['tabs'] as $tab) {
                $this->assertSame(MenuUtil::getTabUrl($submenu['tab']), $map[$tab]);
            }
        }
    }

    public function testAddonsCanExtendTheSubmenus(): void
    {
        $filter = function ($submenus) {
            $submenus['reports'] = [
                'title'      => 'Reports',
                'capability' => 'wpsms_outbox',
                'tab'        => 'reports',
                'tabs'       => ['reports-daily'],
            ];
            return $submenus;
        };
        add_filter('wp_sms_admin_submenus', $filter);

        $this->grant('wpsms_outbox');
        $this->registerMenus();

        remove_filter('wp_sms_admin_submenus', $filter);

        $this->assertSame(['Outbox', 'Reports'], $this->visibleSubmenuTitles());
    }
}
