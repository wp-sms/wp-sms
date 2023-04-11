<?php

use WP_SMS\Helper;
use WP_SMS\Option;

class MobileNumberUtilsTest extends \Codeception\TestCase\WPTestCase
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    public function setUp(): void
    {
        // Before...
        parent::setUp();

        // Your set up methods here.
    }

    public function tearDown(): void
    {
        // Your tear down methods here.

        // Then...
        parent::tearDown();
    }

    public function testSearchUserByMobileNumber()
    {
        // create user
        $userId = static::factory()->user->create();

        // Update mobile field option
        Option::updateOption('add_mobile_field', 'add_mobile_field_in_profile');

        // Add mobile to user
        update_user_meta($userId, Helper::getUserMobileFieldName(), '4186578564');

        $numbers = [
            '4186578564',
            '14186578564',
            '+14186578564',
        ];

        foreach ($numbers as $number) {
            $user = Helper::getUserByPhoneNumber($number);

            $this->assertInstanceOf(WP_User::class, $user);
            $this->assertIsInt($userId, $user->ID);
        }
    }
}
