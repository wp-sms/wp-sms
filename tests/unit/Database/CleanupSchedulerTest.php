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
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['_test_wp_next_scheduled'],
            $GLOBALS['_test_wp_scheduled_events'],
            $GLOBALS['_test_options'],
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
            ->with(90);

        $this->scheduler->run();

        unset($GLOBALS['wpdb']);
    }

    public function testHookNameConstant(): void
    {
        $this->assertSame('wsms_daily_cleanup', CleanupScheduler::HOOK_NAME);
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
