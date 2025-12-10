<?php

namespace unit;

use WP_SMS\Components\Event;
use WP_UnitTestCase;

class EventTest extends WP_UnitTestCase
{
    private $testEventHook = 'wp_sms_test_event';

    /**
     * Cleanup after each test.
     */
    public function tearDown(): void
    {
        // Unschedule any test events
        if (Event::isScheduled($this->testEventHook)) {
            Event::unschedule($this->testEventHook);
        }
        parent::tearDown();
    }

    /**
     * Test isScheduled returns false for non-scheduled event.
     */
    public function testIsScheduledReturnsFalseForNonScheduledEvent()
    {
        $this->assertFalse(Event::isScheduled('non_existent_event'));
    }

    /**
     * Test schedule creates a scheduled event.
     */
    public function testScheduleCreatesScheduledEvent()
    {
        Event::schedule($this->testEventHook, time() + 3600, 'hourly');

        $this->assertTrue(Event::isScheduled($this->testEventHook));
    }

    /**
     * Test schedule does not duplicate existing event.
     */
    public function testScheduleDoesNotDuplicateExistingEvent()
    {
        $timestamp = time() + 3600;

        Event::schedule($this->testEventHook, $timestamp, 'hourly');
        Event::schedule($this->testEventHook, $timestamp + 7200, 'hourly');

        // Should still be scheduled
        $this->assertTrue(Event::isScheduled($this->testEventHook));
    }

    /**
     * Test unschedule removes scheduled event.
     */
    public function testUnscheduleRemovesScheduledEvent()
    {
        Event::schedule($this->testEventHook, time() + 3600, 'hourly');
        $this->assertTrue(Event::isScheduled($this->testEventHook));

        Event::unschedule($this->testEventHook);
        $this->assertFalse(Event::isScheduled($this->testEventHook));
    }

    /**
     * Test unschedule handles non-existent event gracefully.
     */
    public function testUnscheduleHandlesNonExistentEventGracefully()
    {
        // Should not throw an error
        Event::unschedule('non_existent_event');
        $this->assertFalse(Event::isScheduled('non_existent_event'));
    }

    /**
     * Test get returns false for non-scheduled event.
     */
    public function testGetReturnsFalseForNonScheduledEvent()
    {
        $result = Event::get('non_existent_event');
        $this->assertFalse($result);
    }

    /**
     * Test get returns event object for scheduled event.
     */
    public function testGetReturnsEventObjectForScheduledEvent()
    {
        Event::schedule($this->testEventHook, time() + 3600, 'hourly');

        $result = Event::get($this->testEventHook);
        $this->assertIsObject($result);
        $this->assertEquals('hourly', $result->schedule);
    }

    /**
     * Test getSchedules returns array.
     */
    public function testGetSchedulesReturnsArray()
    {
        $schedules = Event::getSchedules();

        $this->assertIsArray($schedules);
        $this->assertNotEmpty($schedules);
    }

    /**
     * Test getSchedules contains expected schedules.
     */
    public function testGetSchedulesContainsExpectedSchedules()
    {
        $schedules = Event::getSchedules();

        $this->assertArrayHasKey('daily', $schedules);
        $this->assertArrayHasKey('weekly', $schedules);
        $this->assertArrayHasKey('biweekly', $schedules);
        $this->assertArrayHasKey('monthly', $schedules);
    }

    /**
     * Test getSchedules daily has correct interval.
     */
    public function testGetSchedulesDailyHasCorrectInterval()
    {
        $schedules = Event::getSchedules();

        $this->assertEquals(DAY_IN_SECONDS, $schedules['daily']['interval']);
    }

    /**
     * Test getSchedules weekly has correct interval.
     */
    public function testGetSchedulesWeeklyHasCorrectInterval()
    {
        $schedules = Event::getSchedules();

        $this->assertEquals(WEEK_IN_SECONDS, $schedules['weekly']['interval']);
    }

    /**
     * Test getSchedules biweekly has correct interval.
     */
    public function testGetSchedulesBiweeklyHasCorrectInterval()
    {
        $schedules = Event::getSchedules();

        $this->assertEquals(2 * WEEK_IN_SECONDS, $schedules['biweekly']['interval']);
    }

    /**
     * Test getSchedules monthly has correct interval.
     */
    public function testGetSchedulesMonthlyHasCorrectInterval()
    {
        $schedules = Event::getSchedules();

        $this->assertEquals(MONTH_IN_SECONDS, $schedules['monthly']['interval']);
    }

    /**
     * Test getSchedules has display names.
     */
    public function testGetSchedulesHasDisplayNames()
    {
        $schedules = Event::getSchedules();

        foreach ($schedules as $schedule) {
            $this->assertArrayHasKey('display', $schedule);
            $this->assertNotEmpty($schedule['display']);
        }
    }

    /**
     * Test getSchedules has next_schedule timestamps.
     */
    public function testGetSchedulesHasNextScheduleTimestamps()
    {
        $schedules = Event::getSchedules();

        foreach ($schedules as $schedule) {
            $this->assertArrayHasKey('next_schedule', $schedule);
            $this->assertIsInt($schedule['next_schedule']);
            $this->assertGreaterThan(time(), $schedule['next_schedule']);
        }
    }

    /**
     * Test getSchedules has start and end dates.
     */
    public function testGetSchedulesHasStartAndEndDates()
    {
        $schedules = Event::getSchedules();

        foreach ($schedules as $schedule) {
            $this->assertArrayHasKey('start', $schedule);
            $this->assertArrayHasKey('end', $schedule);
        }
    }

    /**
     * Test reschedule does nothing for non-scheduled event.
     */
    public function testRescheduleDoesNothingForNonScheduledEvent()
    {
        Event::reschedule('non_existent_event', 'daily');

        // Should not create a new event
        $this->assertFalse(Event::isScheduled('non_existent_event'));
    }

    /**
     * Test schedule with callback adds action.
     */
    public function testScheduleWithCallbackAddsAction()
    {
        $callbackCalled = false;
        $callback       = function () use (&$callbackCalled) {
            $callbackCalled = true;
        };

        Event::schedule($this->testEventHook, time() + 3600, 'hourly', $callback);

        // Verify action is added
        $this->assertTrue(has_action($this->testEventHook) !== false);
    }
}
