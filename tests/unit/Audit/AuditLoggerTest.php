<?php

namespace WSms\Tests\Unit\Audit;

use PHPUnit\Framework\TestCase;
use WSms\Audit\AuditLogger;
use WSms\Enums\EventType;
use WSms\Tests\Support\WpdbFake;

class AuditLoggerTest extends TestCase
{
    private AuditLogger $logger;
    private WpdbFake $wpdb;

    protected function setUp(): void
    {
        $this->wpdb = new WpdbFake();
        $GLOBALS['wpdb'] = $this->wpdb;
        $GLOBALS['_test_options']['wsms_auth_settings'] = ['log_verbosity' => 'verbose'];
        $GLOBALS['_test_do_action_calls'] = [];
        $GLOBALS['_test_apply_filters'] = [];
        unset($_SERVER['HTTP_USER_AGENT']);

        $this->logger = new AuditLogger();
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['wpdb'],
            $GLOBALS['_test_do_action_calls'],
            $GLOBALS['_test_apply_filters'],
        );
        $GLOBALS['_test_options'] = [];
    }

    public function testLogInsertsBasicData(): void
    {
        $this->logger->log(EventType::LoginSuccess, 'success', 1);

        $this->assertCount(1, $this->wpdb->inserts);
        $data = $this->wpdb->inserts[0]['data'];
        $this->assertSame('login_success', $data['event']);
        $this->assertSame('success', $data['status']);
        $this->assertSame(1, $data['user_id']);
    }

    public function testChannelIdAutoExtractedFromMeta(): void
    {
        $this->logger->log(EventType::MfaEnrolled, 'success', 1, [
            'channel' => 'phone',
        ]);

        $data = $this->wpdb->inserts[0]['data'];
        $this->assertSame('phone', $data['channel_id']);
    }

    public function testExplicitChannelIdTakesPrecedence(): void
    {
        $this->logger->log(EventType::MfaEnrolled, 'success', 1, [
            'channel' => 'phone',
        ], 'totp');

        $data = $this->wpdb->inserts[0]['data'];
        $this->assertSame('totp', $data['channel_id']);
    }

    public function testNoChannelIdWhenNotProvided(): void
    {
        $this->logger->log(EventType::LoginSuccess, 'success', 1);

        $data = $this->wpdb->inserts[0]['data'];
        $this->assertArrayNotHasKey('channel_id', $data);
    }

    public function testFilterCanModifyLogData(): void
    {
        $GLOBALS['_test_apply_filters']['wsms_audit_log_entry'] = function ($data) {
            $data['custom_field'] = 'test_value';
            return $data;
        };

        $this->logger->log(EventType::LoginSuccess, 'success', 1);

        $data = $this->wpdb->inserts[0]['data'];
        $this->assertSame('test_value', $data['custom_field']);
    }

    public function testFilterReturningNullSuppressesLog(): void
    {
        $GLOBALS['_test_apply_filters']['wsms_audit_log_entry'] = fn() => null;

        $this->logger->log(EventType::LoginSuccess, 'success', 1);

        $this->assertCount(0, $this->wpdb->inserts);
    }

    public function testActionFiredAfterWrite(): void
    {
        $this->logger->log(EventType::LoginSuccess, 'success', 1);

        $actions = array_filter(
            $GLOBALS['_test_do_action_calls'],
            fn($a) => $a['hook'] === 'wsms_audit_log_written',
        );
        $this->assertCount(1, $actions);

        $action = array_values($actions)[0];
        $this->assertSame('login_success', $action['args'][0]['event']);
    }

    public function testActionNotFiredWhenFilterSuppresses(): void
    {
        $GLOBALS['_test_apply_filters']['wsms_audit_log_entry'] = fn() => null;

        $this->logger->log(EventType::LoginSuccess, 'success', 1);

        $actions = array_filter(
            $GLOBALS['_test_do_action_calls'],
            fn($a) => $a['hook'] === 'wsms_audit_log_written',
        );
        $this->assertCount(0, $actions);
    }

    public function testMetaStoredInVerboseMode(): void
    {
        $this->logger->log(EventType::LoginSuccess, 'success', 1, [
            'channel' => 'phone',
            'method'  => 'sms',
        ]);

        $data = $this->wpdb->inserts[0]['data'];
        $this->assertArrayHasKey('meta', $data);
        $decoded = json_decode($data['meta'], true);
        $this->assertSame('sms', $decoded['method']);
    }

    public function testMetaNotStoredInMinimalMode(): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = ['log_verbosity' => 'minimal'];
        $logger = new AuditLogger();

        $logger->log(EventType::LoginSuccess, 'success', 1, ['key' => 'val']);

        $data = $this->wpdb->inserts[0]['data'];
        $this->assertArrayNotHasKey('meta', $data);
        $this->assertArrayNotHasKey('ip_address', $data);
    }

    public function testDateRangeFiltering(): void
    {
        $result = $this->logger->getEvents([
            'date_from' => '2025-01-01',
            'date_to'   => '2025-12-31',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('total', $result);
    }

    public function testDeleteAllWithNoFilters(): void
    {
        $deleted = $this->logger->deleteAll();

        $this->assertCount(1, $this->wpdb->queries);
        $this->assertStringContainsString('DELETE FROM', $this->wpdb->queries[0]);
        $this->assertStringNotContainsString('WHERE', $this->wpdb->queries[0]);
    }

    public function testDeleteAllWithFilters(): void
    {
        $deleted = $this->logger->deleteAll(['event' => 'login_success']);

        $this->assertCount(1, $this->wpdb->queries);
        $query = $this->wpdb->queries[0];
        $this->assertStringContainsString('DELETE FROM', $query);
        $this->assertStringContainsString('WHERE', $query);
        $this->assertStringContainsString('login_success', $query);
    }

    public function testDeleteAllWithDateRange(): void
    {
        $deleted = $this->logger->deleteAll([
            'date_from' => '2025-01-01',
            'date_to'   => '2025-06-30',
        ]);

        $this->assertCount(1, $this->wpdb->queries);
        $query = $this->wpdb->queries[0];
        $this->assertStringContainsString('2025-01-01', $query);
        $this->assertStringContainsString('2025-06-30', $query);
    }
}
