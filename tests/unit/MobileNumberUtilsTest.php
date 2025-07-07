<?php

namespace unit;

use Faker\Factory;
use WP_SMS\Helper;
use WP_SMS\Settings\Option;
use WP_UnitTestCase;
use WP_User;

class MobileNumberUtilsTest extends WP_UnitTestCase
{
    /**
     * @var \Faker\Generator
     */
    protected $faker;

    /**
     * Setup before each test.
     */
    public function setUp(): void
    {
        parent::setUp();

        // Initialize Faker
        $this->faker = Factory::create();
    }

    /**
     * Tear down after each test.
     */
    public function tearDown(): void
    {
        // Clean up.

        parent::tearDown();
    }

    public function testSearchUserByMobileNumber()
    {
        // Create user
        $userId = $this->factory()->user->create();

        // Update mobile field option
        Option::updateOption('add_mobile_field', 'add_mobile_field_in_profile');

        // Generate a random phone number
        $mobileNumber = $this->faker->e164PhoneNumber;

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

        // Generate a random phone number
        $mobileNumber = $this->faker->e164PhoneNumber;

        // Add mobile to user
        update_user_meta($userId, Helper::getUserMobileFieldName(), $mobileNumber);

        // Create WooCommerce order
        $order = wc_create_order();
        $order->set_billing_first_name($this->faker->firstName);
        $order->set_billing_last_name($this->faker->lastName);
        $order->set_billing_address_1($this->faker->streetAddress);
        $order->set_billing_postcode($this->faker->postcode);
        $order->set_billing_email($this->faker->email);
        $order->set_billing_country('US');
        $order->set_billing_city($this->faker->city);
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

        // Generate a random phone number
        $mobileNumber = $this->faker->e164PhoneNumber;

        // Create WooCommerce order
        $order = wc_create_order();
        $order->set_billing_first_name($this->faker->firstName);
        $order->set_billing_last_name($this->faker->lastName);
        $order->set_billing_address_1($this->faker->streetAddress);
        $order->set_billing_postcode($this->faker->postcode);
        $order->set_billing_email($this->faker->email);
        $order->set_billing_country('US');
        $order->set_billing_city($this->faker->city);
        $order->set_billing_phone($mobileNumber);
        $order->save();
        $order->save_meta_data();

        $customerMobileNumber = Helper::getWooCommerceCustomerNumberByOrderId($order->get_id());

        $this->assertStringContainsString($mobileNumber, $customerMobileNumber);
    }
}
