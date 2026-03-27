<?php

namespace WSms\Tests\Unit\Audit;

use PHPUnit\Framework\TestCase;
use WSms\Audit\AuditLogger;
use WSms\Database\Connection;
use WSms\Enums\EventType;
use WSms\Event\Contracts\EventDispatcherInterface;
use WSms\Event\Events\AuthEvent;
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

        $this->logger = new AuditLogger(new Connection($this->wpdb));
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
        $dispatched = [];
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(function ($event) use (&$dispatched) {
            $dispatched[] = $event;
            return $event;
        });
        $this->logger->setEventDispatcher($dispatcher);

        $this->logger->log(EventType::LoginSuccess, 'success', 1);

        $authEvents = array_filter($dispatched, fn($e) => $e instanceof AuthEvent);
        $this->assertCount(1, $authEvents);

        $event = array_values($authEvents)[0];
        $this->assertSame(EventType::LoginSuccess, $event->eventType);
        $this->assertSame('success', $event->status);
        $this->assertSame(1, $event->userId);
    }

    public function testActionNotFiredWhenFilterSuppresses(): void
    {
        $GLOBALS['_test_apply_filters']['wsms_audit_log_entry'] = fn() => null;

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->never())->method('dispatch');
        $this->logger->setEventDispatcher($dispatcher);

        $this->logger->log(EventType::LoginSuccess, 'success', 1);
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
        $logger = new AuditLogger(new Connection($this->wpdb));

        $logger->log(EventType::LoginSuccess, 'success', 1, ['key' => 'val']);

        $data = $this->wpdb->inserts[0]['data'];
        $this->assertArrayNotHasKey('meta', $data);
        $this->assertArrayNotHasKey('ip_address', $data);
        $this->assertArrayNotHasKey('geo_country', $data);
    }

    public function testGeoCountryStoredWhenHeaderPresent(): void
    {
        $_SERVER['HTTP_CF_IPCOUNTRY'] = 'DE';

        $this->logger->log(EventType::LoginSuccess, 'success', 1);

        $data = $this->wpdb->inserts[0]['data'];
        $this->assertSame('DE', $data['geo_country']);

        unset($_SERVER['HTTP_CF_IPCOUNTRY']);
    }

    public function testGeoCountryNullWhenNoHeader(): void
    {
        unset($_SERVER['HTTP_CF_IPCOUNTRY']);

        $this->logger->log(EventType::LoginSuccess, 'success', 1);

        $data = $this->wpdb->inserts[0]['data'];
        $this->assertNull($data['geo_country']);
    }

    public function testGeoCountryPreservesTorMarker(): void
    {
        $_SERVER['HTTP_CF_IPCOUNTRY'] = 'T1';

        $this->logger->log(EventType::LoginSuccess, 'success', 1);

        $data = $this->wpdb->inserts[0]['data'];
        $this->assertSame('T1', $data['geo_country']);

        unset($_SERVER['HTTP_CF_IPCOUNTRY']);
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
