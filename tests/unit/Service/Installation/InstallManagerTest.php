<?php

namespace WSms\Tests\Unit\Service\Installation;

use PHPUnit\Framework\TestCase;
use WSms\Database\CleanupScheduler;
use WSms\Service\Installation\InstallManager;
use WSms\Tests\Support\WpdbFake;

/**
 * Tests the multisite iteration logic in InstallManager.
 *
 * Uses a subclass that overrides only the leaf methods (activateSingleSite,
 * deactivateSingleSite) to stub Migrator calls. The iteration logic in
 * activate/deactivate/onNewSiteCreated/onSiteDeleted is the real parent code.
 */
class InstallManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['_test_is_multisite'] = false;
        $GLOBALS['_test_sites'] = [];
        $GLOBALS['_test_switched_blog_calls'] = [];
        $GLOBALS['_test_restore_blog_calls'] = 0;
        $GLOBALS['_test_options'] = [];
        $GLOBALS['_test_transients'] = [];
        $GLOBALS['_test_wp_next_scheduled'] = [];
        $GLOBALS['_test_wp_scheduled_events'] = [];
        $GLOBALS['_test_active_sitewide_plugins'] = [];

        TestableInstallManager::$activateCalls = 0;
        TestableInstallManager::$deactivateCalls = 0;

        // Migrator::dropTables() needs $wpdb with prefix and query().
        $GLOBALS['wpdb'] = new WpdbFake();
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['_test_is_multisite'],
            $GLOBALS['_test_sites'],
            $GLOBALS['_test_switched_blog_calls'],
            $GLOBALS['_test_restore_blog_calls'],
            $GLOBALS['_test_active_sitewide_plugins'],
        );

        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // activate()
    // ---------------------------------------------------------------

    public function testActivateSingleSiteDoesNotSwitchBlogs(): void
    {
        TestableInstallManager::activate(false);

        $this->assertSame([], $GLOBALS['_test_switched_blog_calls']);
        $this->assertSame(1, TestableInstallManager::$activateCalls);
    }

    public function testActivateSingleSiteSetsDefaultSettings(): void
    {
        TestableInstallManager::activate(false);

        $settings = $GLOBALS['_test_options']['wsms_auth_settings'];
        $this->assertTrue($settings['password']['enabled']);
        $this->assertTrue($settings['email']['enabled']);
        $this->assertFalse($settings['phone']['enabled']);
    }

    public function testActivateNetworkWideIteratesAllSites(): void
    {
        $GLOBALS['_test_is_multisite'] = true;
        $GLOBALS['_test_sites'] = [1, 2, 3];

        TestableInstallManager::activate(true);

        $this->assertSame([1, 2, 3], $GLOBALS['_test_switched_blog_calls']);
        $this->assertSame(3, $GLOBALS['_test_restore_blog_calls']);
        $this->assertSame(3, TestableInstallManager::$activateCalls);
    }

    public function testActivateNetworkWideFalseOnMultisiteRunsOnce(): void
    {
        $GLOBALS['_test_is_multisite'] = true;

        TestableInstallManager::activate(false);

        $this->assertSame([], $GLOBALS['_test_switched_blog_calls']);
        $this->assertSame(1, TestableInstallManager::$activateCalls);
    }

    // ---------------------------------------------------------------
    // deactivate()
    // ---------------------------------------------------------------

    public function testDeactivateSingleSiteClearsScheduledHook(): void
    {
        $GLOBALS['_test_wp_next_scheduled'][CleanupScheduler::HOOK_NAME] = time();

        TestableInstallManager::deactivate(false);

        $this->assertArrayNotHasKey(CleanupScheduler::HOOK_NAME, $GLOBALS['_test_wp_next_scheduled']);
        $this->assertSame([], $GLOBALS['_test_switched_blog_calls']);
        $this->assertSame(1, TestableInstallManager::$deactivateCalls);
    }

    public function testDeactivateNetworkWideIteratesAllSites(): void
    {
        $GLOBALS['_test_is_multisite'] = true;
        $GLOBALS['_test_sites'] = [1, 2];

        TestableInstallManager::deactivate(true);

        $this->assertSame([1, 2], $GLOBALS['_test_switched_blog_calls']);
        $this->assertSame(2, $GLOBALS['_test_restore_blog_calls']);
        $this->assertSame(2, TestableInstallManager::$deactivateCalls);
    }

    // ---------------------------------------------------------------
    // onNewSiteCreated()
    // ---------------------------------------------------------------

    public function testOnNewSiteCreatedProvisionsSiteWhenNetworkActive(): void
    {
        $GLOBALS['_test_is_multisite'] = true;
        $GLOBALS['_test_active_sitewide_plugins'] = ['wp-sms/wp-sms.php' => true];

        $site = new \WP_Site();
        $site->blog_id = '5';

        TestableInstallManager::onNewSiteCreated($site);

        $this->assertSame([5], $GLOBALS['_test_switched_blog_calls']);
        $this->assertSame(1, $GLOBALS['_test_restore_blog_calls']);
        $this->assertSame(1, TestableInstallManager::$activateCalls);
    }

    public function testOnNewSiteCreatedSkipsWhenNotNetworkActive(): void
    {
        $GLOBALS['_test_is_multisite'] = true;
        $GLOBALS['_test_active_sitewide_plugins'] = [];

        $site = new \WP_Site();
        $site->blog_id = '5';

        TestableInstallManager::onNewSiteCreated($site);

        $this->assertSame([], $GLOBALS['_test_switched_blog_calls']);
        $this->assertSame(0, TestableInstallManager::$activateCalls);
    }

    public function testOnNewSiteCreatedSkipsOnSingleSite(): void
    {
        $GLOBALS['_test_is_multisite'] = false;

        $site = new \WP_Site();
        $site->blog_id = '1';

        TestableInstallManager::onNewSiteCreated($site);

        $this->assertSame([], $GLOBALS['_test_switched_blog_calls']);
        $this->assertSame(0, TestableInstallManager::$activateCalls);
    }

    // ---------------------------------------------------------------
    // onSiteDeleted()
    // ---------------------------------------------------------------

    public function testOnSiteDeletedCleansUpWhenNetworkActive(): void
    {
        $GLOBALS['_test_is_multisite'] = true;
        $GLOBALS['_test_active_sitewide_plugins'] = ['wp-sms/wp-sms.php' => true];
        $GLOBALS['_test_options']['wsms_auth_settings'] = ['some' => 'settings'];

        $site = new \WP_Site();
        $site->blog_id = '3';

        TestableInstallManager::onSiteDeleted($site);

        $this->assertSame([3], $GLOBALS['_test_switched_blog_calls']);
        $this->assertSame(1, $GLOBALS['_test_restore_blog_calls']);
        $this->assertSame(1, TestableInstallManager::$deactivateCalls);
        $this->assertArrayNotHasKey('wsms_auth_settings', $GLOBALS['_test_options']);
    }

    public function testOnSiteDeletedSkipsWhenNotNetworkActive(): void
    {
        $GLOBALS['_test_is_multisite'] = true;
        $GLOBALS['_test_active_sitewide_plugins'] = [];

        $site = new \WP_Site();
        $site->blog_id = '3';

        TestableInstallManager::onSiteDeleted($site);

        $this->assertSame([], $GLOBALS['_test_switched_blog_calls']);
        $this->assertSame(0, TestableInstallManager::$deactivateCalls);
    }
}

/**
 * Subclass that overrides only the leaf methods to stub Migrator (DB) calls.
 * The iteration logic (activate, deactivate, onNewSiteCreated, onSiteDeleted)
 * runs from the real parent class.
 */
class TestableInstallManager extends InstallManager
{
    public static int $activateCalls = 0;
    public static int $deactivateCalls = 0;

    protected static function activateSingleSite(): void
    {
        self::$activateCalls++;

        // Run everything except Migrator::createTables() (needs real DB).
        set_transient('wsms_flush_rewrite', '1');

        if (!wp_next_scheduled(CleanupScheduler::HOOK_NAME)) {
            wp_schedule_event(time(), 'daily', CleanupScheduler::HOOK_NAME);
        }

        add_option('wsms_auth_settings', [
            'phone'    => ['enabled' => false],
            'email'    => ['enabled' => true],
            'password' => ['enabled' => true],
        ]);
    }

    protected static function deactivateSingleSite(): void
    {
        self::$deactivateCalls++;
        wp_clear_scheduled_hook(CleanupScheduler::HOOK_NAME);
    }
}
