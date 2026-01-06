<?php

namespace unit;

use WP_SMS\Helper;
use WP_SMS\Option;
use WP_UnitTestCase;
use WP_User;

class MobileNumberUtilsTest extends WP_UnitTestCase
{
    private static $counter = 0;

    /**
     * Setup before each test.
     */
    public function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Tear down after each test.
     */
    public function tearDown(): void
    {
        parent::tearDown();
    }

    public function testSearchUserByMobileNumber()
    {
        // Create user
        $userId = $this->factory()->user->create();

        // Update mobile field option
        Option::updateOption('add_mobile_field', 'add_mobile_field_in_profile');

        // Static phone number
        $mobileNumber = '+12025550' . str_pad(++self::$counter, 3, '0', STR_PAD_LEFT);

        // Add mobile to user
        update_user_meta($userId, Helper::getUserMobileFieldName(), $mobileNumber);

        $numbers = [
            $mobileNumber,
            '1' . $mobileNumber,
        ];

        foreach ($numbers as $number) {
            $user = Helper::getUserByPhoneNumber($number);
            $this->assertInstanceOf(WP_User::class, $user);
            $this->assertEquals($userId, $user->ID);
        }
    }

    public function testSearchWooCommerceCustomerMobileNumberByOrderId()
    {
        // Create user
        $userId = $this->factory()->user->create();

        // Update mobile field option
        Option::updateOption('add_mobile_field', 'use_phone_field_in_wc_billing');

        // Static phone number
        $mobileNumber = '+12025551' . str_pad(++self::$counter, 3, '0', STR_PAD_LEFT);

        // Add mobile to user
        update_user_meta($userId, Helper::getUserMobileFieldName(), $mobileNumber);

        // Create WooCommerce order
        $order = wc_create_order();
        $order->set_billing_first_name('John');
        $order->set_billing_last_name('Doe');
        $order->set_billing_address_1('123 Main St');
        $order->set_billing_postcode('10001');
        $order->set_billing_email('john.doe@example.com');
        $order->set_billing_country('US');
        $order->set_billing_city('New York');
        $order->set_billing_phone($mobileNumber);
        $order->set_customer_id($userId);
        $order->save();
        $order->save_meta_data();

        $customerMobileNumber = Helper::getWooCommerceCustomerNumberByOrderId($order->get_id());

        $this->assertStringContainsString($mobileNumber, $customerMobileNumber);
    }

    public function testSearchWooCommerceGuestMobileNumberByOrderId()
    {
        // Update mobile field option
        Option::updateOption('add_mobile_field', 'use_phone_field_in_wc_billing');

        // Static phone number
        $mobileNumber = '+12025552' . str_pad(++self::$counter, 3, '0', STR_PAD_LEFT);

        // Create WooCommerce order
        $order = wc_create_order();
        $order->set_billing_first_name('Jane');
        $order->set_billing_last_name('Smith');
        $order->set_billing_address_1('456 Oak Ave');
        $order->set_billing_postcode('90210');
        $order->set_billing_email('jane.smith@example.com');
        $order->set_billing_country('US');
        $order->set_billing_city('Los Angeles');
        $order->set_billing_phone($mobileNumber);
        $order->save();
        $order->save_meta_data();

        $customerMobileNumber = Helper::getWooCommerceCustomerNumberByOrderId($order->get_id());

        $this->assertStringContainsString($mobileNumber, $customerMobileNumber);
    }
}
