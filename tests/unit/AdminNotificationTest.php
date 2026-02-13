<?php

namespace unit;

use WP_SMS\Admin\Notification\NotificationFactory;
use WP_SMS\Admin\Notification\NotificationProcessor;
use WP_UnitTestCase;

class AdminNotificationTest extends WP_UnitTestCase
{
    /**
     * Sample notifications data for testing.
     */
    private function getSampleNotifications()
    {
        return [
            'data' => [
                [
                    'id'           => 1,
                    'title'        => 'Test Notification 1',
                    'content'      => 'This is test notification 1',
                    'type'         => 'info',
                    'activated_at' => '2024-01-15 10:00:00',
                    'tags'         => [],
                    'dismiss'      => false,
                ],
                [
                    'id'           => 2,
                    'title'        => 'Test Notification 2',
                    'content'      => 'This is test notification 2',
                    'type'         => 'warning',
                    'activated_at' => '2024-01-16 10:00:00',
                    'tags'         => [],
                    'dismiss'      => false,
                ],
                [
                    'id'           => 3,
                    'title'        => 'Dismissed Notification',
                    'content'      => 'This notification is dismissed',
                    'type'         => 'info',
                    'activated_at' => '2024-01-14 10:00:00',
                    'tags'         => [],
                    'dismiss'      => true,
                ],
            ],
        ];
    }

    /**
     * Setup before each test.
     */
    public function setUp(): void
    {
        parent::setUp();
        delete_option('wp_sms_notifications');
    }

    /**
     * Cleanup after each test.
     */
    public function tearDown(): void
    {
        delete_option('wp_sms_notifications');
        parent::tearDown();
    }

    // ==========================================
    // NotificationFactory Tests
    // ==========================================

    /**
     * Test getRawNotificationsData returns empty array when no data.
     */
    public function testGetRawNotificationsDataReturnsEmptyArrayWhenNoData()
    {
        $result = NotificationFactory::getRawNotificationsData();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test getRawNotificationsData returns stored data.
     */
    public function testGetRawNotificationsDataReturnsStoredData()
    {
        $notifications = $this->getSampleNotifications();
        update_option('wp_sms_notifications', $notifications);

        $result = NotificationFactory::getRawNotificationsData();

        $this->assertEquals($notifications, $result);
    }

    /**
     * Test getAllNotifications returns array.
     */
    public function testGetAllNotificationsReturnsArray()
    {
        $result = NotificationFactory::getAllNotifications();

        $this->assertIsArray($result);
    }

    /**
     * Test getAllNotifications returns decorated notifications.
     */
    public function testGetAllNotificationsReturnsDecoratedNotifications()
    {
        $notifications = $this->getSampleNotifications();
        update_option('wp_sms_notifications', $notifications);

        $result = NotificationFactory::getAllNotifications();

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(\WP_SMS\Decorators\NotificationDecorator::class, $result[0]);
    }

    /**
     * Test hasUpdatedNotifications returns false when no data.
     */
    public function testHasUpdatedNotificationsReturnsFalseWhenNoData()
    {
        $result = NotificationFactory::hasUpdatedNotifications();

        $this->assertFalse($result);
    }

    /**
     * Test hasUpdatedNotifications returns true when undismissed notifications exist.
     */
    public function testHasUpdatedNotificationsReturnsTrueWhenUndismissedExist()
    {
        $notifications = $this->getSampleNotifications();
        update_option('wp_sms_notifications', $notifications);

        $result = NotificationFactory::hasUpdatedNotifications();

        $this->assertTrue($result);
    }

    /**
     * Test hasUpdatedNotifications returns false when all dismissed.
     */
    public function testHasUpdatedNotificationsReturnsFalseWhenAllDismissed()
    {
        $notifications = [
            'data' => [
                ['id' => 1, 'dismiss' => true, 'tags' => []],
                ['id' => 2, 'dismiss' => true, 'tags' => []],
            ],
        ];
        update_option('wp_sms_notifications', $notifications);

        $result = NotificationFactory::hasUpdatedNotifications();

        $this->assertFalse($result);
    }

    /**
     * Test getNewNotificationCount returns zero when no data.
     */
    public function testGetNewNotificationCountReturnsZeroWhenNoData()
    {
        $result = NotificationFactory::getNewNotificationCount();

        $this->assertEquals(0, $result);
    }

    /**
     * Test getNewNotificationCount returns correct count.
     */
    public function testGetNewNotificationCountReturnsCorrectCount()
    {
        $notifications = $this->getSampleNotifications();
        update_option('wp_sms_notifications', $notifications);

        $result = NotificationFactory::getNewNotificationCount();

        // 2 undismissed notifications
        $this->assertEquals(2, $result);
    }

    /**
     * Test getNewNotificationCount returns zero when all dismissed.
     */
    public function testGetNewNotificationCountReturnsZeroWhenAllDismissed()
    {
        $notifications = [
            'data' => [
                ['id' => 1, 'dismiss' => true, 'tags' => []],
                ['id' => 2, 'dismiss' => true, 'tags' => []],
            ],
        ];
        update_option('wp_sms_notifications', $notifications);

        $result = NotificationFactory::getNewNotificationCount();

        $this->assertEquals(0, $result);
    }

    // ==========================================
    // NotificationProcessor Tests
    // ==========================================

    /**
     * Test filterNotificationsByTags returns empty array for empty input.
     */
    public function testFilterNotificationsByTagsReturnsEmptyForEmptyInput()
    {
        $result = NotificationProcessor::filterNotificationsByTags([]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test filterNotificationsByTags keeps notifications without tags.
     */
    public function testFilterNotificationsByTagsKeepsNotificationsWithoutTags()
    {
        $notifications = [
            ['id' => 1, 'title' => 'Test', 'tags' => []],
            ['id' => 2, 'title' => 'Test 2'],
        ];

        $result = NotificationProcessor::filterNotificationsByTags($notifications);

        $this->assertCount(2, $result);
    }

    /**
     * Test decorateNotifications returns empty array for empty input.
     */
    public function testDecorateNotificationsReturnsEmptyForEmptyInput()
    {
        $result = NotificationProcessor::decorateNotifications([]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test decorateNotifications returns decorated objects.
     */
    public function testDecorateNotificationsReturnsDecoratedObjects()
    {
        $notifications = [
            ['id' => 1, 'title' => 'Test'],
            ['id' => 2, 'title' => 'Test 2'],
        ];

        $result = NotificationProcessor::decorateNotifications($notifications);

        $this->assertCount(2, $result);
        $this->assertInstanceOf(\WP_SMS\Decorators\NotificationDecorator::class, $result[0]);
    }

    /**
     * Test dismissNotification marks notification as dismissed.
     */
    public function testDismissNotificationMarksAsDismissed()
    {
        $notifications = $this->getSampleNotifications();
        update_option('wp_sms_notifications', $notifications);

        NotificationProcessor::dismissNotification(1);

        $updated = NotificationFactory::getRawNotificationsData();

        $this->assertTrue($updated['data'][0]['dismiss']);
    }

    /**
     * Test dismissNotification returns true.
     */
    public function testDismissNotificationReturnsTrue()
    {
        $notifications = $this->getSampleNotifications();
        update_option('wp_sms_notifications', $notifications);

        $result = NotificationProcessor::dismissNotification(1);

        $this->assertTrue($result);
    }

    /**
     * Test dismissNotification handles non-existent ID.
     */
    public function testDismissNotificationHandlesNonExistentId()
    {
        $notifications = $this->getSampleNotifications();
        update_option('wp_sms_notifications', $notifications);

        $result = NotificationProcessor::dismissNotification(999);

        $this->assertTrue($result);
    }

    /**
     * Test dismissAllNotifications marks all as dismissed.
     */
    public function testDismissAllNotificationsMarksAllAsDismissed()
    {
        $notifications = $this->getSampleNotifications();
        update_option('wp_sms_notifications', $notifications);

        NotificationProcessor::dismissAllNotifications();

        $updated = NotificationFactory::getRawNotificationsData();

        foreach ($updated['data'] as $notification) {
            $this->assertTrue($notification['dismiss']);
        }
    }

    /**
     * Test dismissAllNotifications returns true.
     */
    public function testDismissAllNotificationsReturnsTrue()
    {
        $notifications = $this->getSampleNotifications();
        update_option('wp_sms_notifications', $notifications);

        $result = NotificationProcessor::dismissAllNotifications();

        $this->assertTrue($result);
    }

    /**
     * Test dismissAllNotifications handles empty data.
     */
    public function testDismissAllNotificationsHandlesEmptyData()
    {
        $result = NotificationProcessor::dismissAllNotifications();

        $this->assertTrue($result);
    }

    /**
     * Test syncNotifications preserves dismissed state.
     */
    public function testSyncNotificationsPreservesDismissedState()
    {
        // Set old notifications with dismissed items
        $oldNotifications = [
            'data' => [
                ['id' => 1, 'dismiss' => true],
                ['id' => 2, 'dismiss' => false],
            ],
        ];
        update_option('wp_sms_notifications', $oldNotifications);

        // New notifications
        $newNotifications = [
            'data' => [
                ['id' => 1, 'title' => 'Updated 1'],
                ['id' => 2, 'title' => 'Updated 2'],
                ['id' => 3, 'title' => 'New notification'],
            ],
        ];

        $result = NotificationProcessor::syncNotifications($newNotifications);

        // ID 1 should still be dismissed
        $this->assertTrue($result['data'][0]['dismiss']);
        // ID 2 should not have dismiss set
        $this->assertArrayNotHasKey('dismiss', $result['data'][1]);
        // ID 3 is new, should not have dismiss
        $this->assertArrayNotHasKey('dismiss', $result['data'][2]);
    }

    /**
     * Test syncNotifications handles empty old notifications.
     */
    public function testSyncNotificationsHandlesEmptyOldNotifications()
    {
        $newNotifications = [
            'data' => [
                ['id' => 1, 'title' => 'New 1'],
            ],
        ];

        $result = NotificationProcessor::syncNotifications($newNotifications);

        $this->assertCount(1, $result['data']);
        $this->assertArrayNotHasKey('dismiss', $result['data'][0]);
    }

    /**
     * Test sortNotificationsByActivatedAt sorts correctly.
     */
    public function testSortNotificationsByActivatedAtSortsCorrectly()
    {
        $notifications = [
            'data' => [
                ['id' => 1, 'activated_at' => '2024-01-10 10:00:00'],
                ['id' => 2, 'activated_at' => '2024-01-15 10:00:00'],
                ['id' => 3, 'activated_at' => '2024-01-12 10:00:00'],
            ],
        ];

        $result = NotificationProcessor::sortNotificationsByActivatedAt($notifications);

        // Should be sorted descending (newest first)
        $this->assertEquals(2, $result['data'][0]['id']);
        $this->assertEquals(3, $result['data'][1]['id']);
        $this->assertEquals(1, $result['data'][2]['id']);
    }

    /**
     * Test sortNotificationsByActivatedAt handles empty data.
     */
    public function testSortNotificationsByActivatedAtHandlesEmptyData()
    {
        $notifications = ['data' => []];

        $result = NotificationProcessor::sortNotificationsByActivatedAt($notifications);

        $this->assertEmpty($result['data']);
    }

    /**
     * Test sortNotificationsByActivatedAt handles missing data key.
     */
    public function testSortNotificationsByActivatedAtHandlesMissingDataKey()
    {
        $notifications = [];

        $result = NotificationProcessor::sortNotificationsByActivatedAt($notifications);

        $this->assertEmpty($result);
    }

    /**
     * Test checkUpdatedNotifications detects new notifications.
     */
    public function testCheckUpdatedNotificationsDetectsNewNotifications()
    {
        // Set old notifications
        $oldNotifications = [
            'data' => [
                ['id' => 1, 'tags' => []],
            ],
        ];
        update_option('wp_sms_notifications', $oldNotifications);

        // New notifications with an additional item
        $newNotifications = [
            'data' => [
                ['id' => 1, 'tags' => []],
                ['id' => 2, 'tags' => []],
            ],
        ];

        $result = NotificationProcessor::checkUpdatedNotifications($newNotifications);

        $this->assertTrue($result['updated']);
    }

    /**
     * Test checkUpdatedNotifications returns false when no new notifications.
     */
    public function testCheckUpdatedNotificationsReturnsFalseWhenNoNew()
    {
        // Set old notifications
        $oldNotifications = [
            'data'    => [
                ['id' => 1, 'tags' => []],
            ],
            'updated' => false,
        ];
        update_option('wp_sms_notifications', $oldNotifications);

        // Same notifications
        $newNotifications = [
            'data' => [
                ['id' => 1, 'tags' => []],
            ],
        ];

        $result = NotificationProcessor::checkUpdatedNotifications($newNotifications);

        $this->assertFalse($result['updated']);
    }

    /**
     * Test annotateNewNotificationCount adds count.
     */
    public function testAnnotateNewNotificationCountAddsCount()
    {
        // Set old notifications
        $oldNotifications = [
            'data'  => [
                ['id' => 1, 'tags' => []],
            ],
            'count' => 0,
        ];
        update_option('wp_sms_notifications', $oldNotifications);

        // New notifications with updated flag
        $newNotifications = [
            'data'    => [
                ['id' => 1, 'tags' => []],
                ['id' => 2, 'tags' => []],
            ],
            'updated' => true,
        ];

        $result = NotificationProcessor::annotateNewNotificationCount($newNotifications);

        $this->assertEquals(1, $result['count']);
    }

    /**
     * Test annotateNewNotificationCount resets count when not updated.
     */
    public function testAnnotateNewNotificationCountResetsWhenNotUpdated()
    {
        // Set old notifications with existing count
        $oldNotifications = [
            'data'  => [
                ['id' => 1, 'tags' => []],
            ],
            'count' => 5,
        ];
        update_option('wp_sms_notifications', $oldNotifications);

        // New notifications without updated flag
        $newNotifications = [
            'data'    => [
                ['id' => 1, 'tags' => []],
            ],
            'updated' => false,
        ];

        $result = NotificationProcessor::annotateNewNotificationCount($newNotifications);

        $this->assertEquals(0, $result['count']);
    }
}
