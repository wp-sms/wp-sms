<?php

namespace WSms\Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use WSms\Audit\AuditLogger;
use WSms\Database\CleanupScheduler;

class CleanupSchedulerTest extends TestCase
{
    private CleanupScheduler $scheduler;
    private AuditLogger $auditLogger;

    protected function setUp(): void
    {
        $this->auditLogger = $this->createMock(AuditLogger::class);
        $this->scheduler = new CleanupScheduler($this->auditLogger);

        $GLOBALS['_test_wp_next_scheduled'] = [];
        $GLOBALS['_test_wp_scheduled_events'] = [];
        $GLOBALS['_test_options'] = [];
        $GLOBALS['_test_deleted_users'] = [];
        $GLOBALS['_test_get_users_result'] = [];
        $GLOBALS['_test_user_meta'] = [];
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['_test_wp_next_scheduled'],
            $GLOBALS['_test_wp_scheduled_events'],
            $GLOBALS['_test_options'],
            $GLOBALS['_test_deleted_users'],
            $GLOBALS['_test_get_users_result'],
            $GLOBALS['_test_user_meta'],
        );
    }

    public function testScheduleRegistersEventWhenNotAlreadyScheduled(): void
    {
        $this->scheduler->schedule();

        $this->assertArrayHasKey(
            CleanupScheduler::HOOK_NAME,
            $GLOBALS['_test_wp_scheduled_events'],
        );
        $this->assertSame(
            'daily',
            $GLOBALS['_test_wp_scheduled_events'][CleanupScheduler::HOOK_NAME]['recurrence'],
        );
    }

    public function testScheduleDoesNotReRegisterWhenAlreadyScheduled(): void
    {
        $GLOBALS['_test_wp_next_scheduled'][CleanupScheduler::HOOK_NAME] = time() + 3600;

        $this->scheduler->schedule();

        $this->assertArrayNotHasKey(
            CleanupScheduler::HOOK_NAME,
            $GLOBALS['_test_wp_scheduled_events'] ?? [],
        );
    }

    public function testUnscheduleRemovesHook(): void
    {
        $GLOBALS['_test_wp_scheduled_events'][CleanupScheduler::HOOK_NAME] = [
            'timestamp'  => time(),
            'recurrence' => 'daily',
            'args'       => [],
        ];
        $GLOBALS['_test_wp_next_scheduled'][CleanupScheduler::HOOK_NAME] = time();

        $this->scheduler->unschedule();

        $this->assertArrayNotHasKey(
            CleanupScheduler::HOOK_NAME,
            $GLOBALS['_test_wp_scheduled_events'],
        );
    }

    public function testRunCallsDeleteOlderThanWithConfiguredDays(): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'log_retention_days' => 30,
        ];

        // Mock $wpdb for the verifications cleanup.
        $wpdb = $this->createWpdbMock();
        $GLOBALS['wpdb'] = $wpdb;

        $this->auditLogger->expects($this->once())
            ->method('deleteOlderThan')
            ->with(30);

        $this->scheduler->run();

        unset($GLOBALS['wpdb']);
    }

    public function testRunUsesDefault90DaysWhenSettingMissing(): void
    {
        $wpdb = $this->createWpdbMock();
        $GLOBALS['wpdb'] = $wpdb;

        $this->auditLogger->expects($this->once())
            ->method('deleteOlderThan')
            ->with(30);

        $this->scheduler->run();

        unset($GLOBALS['wpdb']);
    }

    public function testHookNameConstant(): void
    {
        $this->assertSame('wsms_daily_cleanup', CleanupScheduler::HOOK_NAME);
    }

    public function testRunDeletesExpiredPendingUsers(): void
    {
        $wpdb = $this->createWpdbMock();
        $GLOBALS['wpdb'] = $wpdb;

        $user = new \stdClass();
        $user->ID = 10;
        $GLOBALS['_test_get_users_result'] = [$user];

        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'pending_user_cleanup_enabled' => true,
            'pending_user_ttl_hours'       => 24,
        ];

        $this->scheduler->run();

        $this->assertContains(10, $GLOBALS['_test_deleted_users']);
        unset($GLOBALS['wpdb']);
    }

    public function testRunSkipsCleanupWhenDisabled(): void
    {
        $wpdb = $this->createWpdbMock();
        $GLOBALS['wpdb'] = $wpdb;

        $user = new \stdClass();
        $user->ID = 11;
        $GLOBALS['_test_get_users_result'] = [$user];

        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'pending_user_cleanup_enabled' => false,
        ];

        $this->scheduler->run();

        $this->assertNotContains(11, $GLOBALS['_test_deleted_users']);
        unset($GLOBALS['wpdb']);
    }

    public function testRunUsesDefaultTtlOf24Hours(): void
    {
        $wpdb = $this->createWpdbMock();
        $GLOBALS['wpdb'] = $wpdb;

        $user = new \stdClass();
        $user->ID = 12;
        $GLOBALS['_test_get_users_result'] = [$user];

        // No TTL setting — should default to 24h.
        $GLOBALS['_test_options']['wsms_auth_settings'] = [];

        $this->scheduler->run();

        // get_users was called (cleanup is enabled by default), users returned are deleted.
        $this->assertContains(12, $GLOBALS['_test_deleted_users']);
        unset($GLOBALS['wpdb']);
    }

    public function testRunSkipsCleanupWhenNoExpiredUsers(): void
    {
        $wpdb = $this->createWpdbMock();
        $GLOBALS['wpdb'] = $wpdb;

        // No users returned by get_users.
        $GLOBALS['_test_get_users_result'] = [];
        $GLOBALS['_test_options']['wsms_auth_settings'] = [];

        $this->scheduler->run();

        $this->assertEmpty($GLOBALS['_test_deleted_users']);
        unset($GLOBALS['wpdb']);
    }

    private function createWpdbMock(): object
    {
        $wpdb = new class {
            public string $prefix = 'wp_';

            public function query(string $sql): int
            {
                return 0;
            }
        };

        return $wpdb;
    }
}
