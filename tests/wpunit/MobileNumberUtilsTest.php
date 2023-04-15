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

    public function testSearchWooCommereCustomerMobileNumberByOrderId()
    {
        // create user
        $userId = static::factory()->user->create();

        // Update mobile field option
        Option::updateOption('add_mobile_field', 'use_phone_field_in_wc_billing');

        // Add mobile to user
        update_user_meta($userId, Helper::getUserMobileFieldName(), '4186578565');

        $order = wc_create_order();
        $order->set_billing_first_name('Mary');
        $order->set_billing_last_name('Christensen');
        $order->set_billing_address_1('179 Allison Avenue');
        $order->set_billing_postcode('3152');
        $order->set_billing_email('marykchristensen@armyspy.com');
        $order->set_billing_country('US');
        $order->set_billing_city('VA');
        $order->set_billing_phone('4186578565');
        $order->set_customer_id($userId);
        $order->save();
        $order->save_meta_data();

        $customerMobileNumber = Helper::getWooCommerceCustomerNumberByOrderId($order->get_id());

        $this->assertStringContainsString('4186578565', $customerMobileNumber);
    }

    public function testSearchWooCommereGuestMobileNumberByOrderId()
    {
        // Update mobile field option
        Option::updateOption('add_mobile_field', 'use_phone_field_in_wc_billing');

        $order = wc_create_order();
        $order->set_billing_first_name('Goldie');
        $order->set_billing_last_name('Ramos');
        $order->set_billing_address_1('4600 Woodhill Avenue');
        $order->set_billing_postcode('21030');
        $order->set_billing_email('goldierramos@armyspy.com');
        $order->set_billing_country('US');
        $order->set_billing_city('MD');
        $order->set_billing_phone('4186578566');
        $order->save();
        $order->save_meta_data();

        $customerMobileNumber = Helper::getWooCommerceCustomerNumberByOrderId($order->get_id());

        $this->assertStringContainsString('4186578566', $customerMobileNumber);
    }
}
