<?php

namespace unit;

use WP_SMS\Helper;
use WP_SMS\Newsletter;
use WP_UnitTestCase;

class NewsletterTest extends WP_UnitTestCase
{
    protected $test_mobile;
    protected $test_name;
    protected $group_id;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup test values
        $this->test_mobile = '+1' . rand(1000000000, 9999999999);
        $this->test_name   = 'Test User';

        // Create a test group
        $group_result = Newsletter::addGroup('Test Group');
        $this->assertEquals('success', $group_result['result']);
        $this->group_id = $group_result['data']['group_ID'];

        // Add a subscriber
        $add_result = Newsletter::addSubscriber(
            $this->test_name,
            $this->test_mobile,
            json_encode([$this->group_id]),
            '1',
            null,
            []
        );
        $this->assertEquals('success', $add_result['result']);
    }

    public function testGetSubscriberByMobileReturnsCorrectSubscriber()
    {
        $subscriber = Newsletter::getSubscriberByMobile($this->test_mobile);

        $this->assertNotNull($subscriber);
        $this->assertEquals($this->test_mobile, $subscriber->mobile);
        $this->assertEquals($this->test_name, $subscriber->name);
    }

    protected function tearDown(): void
    {
        // Cleanup: delete subscriber
        Newsletter::deleteSubscriberByNumber($this->test_mobile, json_encode([$this->group_id]));

        // Cleanup: delete group
        Newsletter::deleteGroup($this->group_id);

        parent::tearDown();
    }
}
