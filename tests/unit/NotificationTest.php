<?php

namespace unit;

use WC_Coupon;
use WC_Customer;
use WP_SMS\Notification\NotificationFactory;
use WP_UnitTestCase;

class NotificationTest extends WP_UnitTestCase
{
    protected $faker;

    /**
     * Setup before each test.
     */
    public function setUp(): void
    {
        parent::setUp();

        // Initialize Faker for generating dynamic test data
        $this->faker = \Faker\Factory::create();
    }

    /**
     * Test that a post can be created and retrieved.
     */
    public function testItWorks()
    {
        $post = static::factory()->post->create_and_get();
        $this->assertInstanceOf(\WP_Post::class, $post);
    }

    /**
     * Test printing registered variables in notification.
     */
    public function testPrintVariables()
    {
        $notification = NotificationFactory::getCustom();
        $notification->registerVariables([
            '%age%'  => $this->faker->numberBetween(18, 60),
            '%name%' => $this->faker->firstName,
        ]);

        $this->assertStringContainsString(
            '<code>%age%</code> <code>%name%</code>',
            $notification->printVariables()
        );
    }

    /**
     * Test custom output message.
     */
    public function testCustomOutputMessage()
    {
        $notification = NotificationFactory::getCustom();
        $variables    = [
            '%age%'  => $this->faker->numberBetween(18, 60),
            '%name%' => $this->faker->firstName,
        ];
        $notification->registerVariables($variables);

        $expectedMessage = "Hello, {$variables['%name%']}, {$variables['%age%']}";
        $this->assertStringContainsString(
            $expectedMessage,
            $notification->getOutputMessage('Hello, %name%, %age%')
        );
    }

    /**
     * Test post-related output message.
     */
    public function testPostOutputMessage()
    {
        $postId       = $this->factory()->post->create();
        $notification = NotificationFactory::getPost($postId);

        $this->assertStringContainsString(
            "Post ID #{$postId}",
            $notification->getOutputMessage('Post ID #%post_id%')
        );
    }

    /**
     * Test comment-related output message.
     */
    public function testCommentOutputMessage()
    {
        $commentId    = $this->factory()->comment->create();
        $notification = NotificationFactory::getComment($commentId);

        $this->assertStringContainsString(
            "Comment ID #{$commentId}",
            $notification->getOutputMessage('Comment ID #%comment_id%')
        );
    }

    /**
     * Test user-related output message.
     */
    public function testUserOutputMessage()
    {
        $userId       = $this->factory()->user->create();
        $notification = NotificationFactory::getUser($userId);

        $this->assertStringContainsString(
            "User ID #{$userId}",
            $notification->getOutputMessage('User ID #%user_id%')
        );
    }

    /**
     * Test subscriber-related output message.
     */
    public function testSubscriberOutputMessage()
    {
        $name         = $this->faker->name;
        $mobile       = $this->faker->phoneNumber;
        $subscriber   = WPSms()->newsletter()::addSubscriber($name, $mobile);
        $notification = NotificationFactory::getSubscriber($subscriber['id']);
        $this->assertStringContainsString(
            "Name: {$name}, Mobile: {$mobile}",
            $notification->getOutputMessage('Name: %subscriber_name%, Mobile: %subscriber_mobile%')
        );
    }

    /**
     * Test coupon notification output.
     */
    public function testCouponNotificationOutput()
    {
        $couponCode   = $this->faker->bothify('???###');
        $couponAmount = $this->faker->randomFloat(2, 10, 100);

        $coupon = new WC_Coupon();
        $coupon->set_code($couponCode);
        $coupon->set_amount($couponAmount);
        $coupon->save();

        $notification = NotificationFactory::getWooCommerceCoupon($coupon);

        $this->assertStringContainsString(
            "Coupon Code : {$couponCode} , Coupon Amount : {$couponAmount}",
            $notification->getOutputMessage('Coupon Code : %coupon_code% , Coupon Amount : %coupon_amount%')
        );
    }

    /**
     * Test WooCommerce customer notification output.
     */
    public function testCustomerNotificationOutput()
    {
        $customer = new WC_Customer();
        $customer->set_email($this->faker->email);
        $customer->set_username($this->faker->userName);
        $customer->set_first_name($this->faker->firstName);
        $customer->set_last_name($this->faker->lastName);
        $customer->save();

        $notification = NotificationFactory::getWooCommerceCustomer($customer);

        $this->assertStringContainsString(
            "Customer Id: {$customer->get_id()}, Customer Email: {$customer->get_email()}, Customer Username: {$customer->get_username()}, Customer Firstname: {$customer->get_first_name()}, Customer Lastname: {$customer->get_last_name()}",
            $notification->getOutputMessage('Customer Id: %customer_id%, Customer Email: %customer_email%, Customer Username: %customer_username%, Customer Firstname: %customer_first_name%, Customer Lastname: %customer_last_name%')
        );
    }

    /**
     * Test that processMessage handles an empty message.
     *
     * Ensures that when the message template is empty,
     * both the parsed output message and parsed variables
     * return as empty values.
     */
    public function testProcessMessageWithEmptyMessage()
    {
        $notification = NotificationFactory::getCustom();

        $reflection = new \ReflectionClass($notification);
        $method     = $reflection->getMethod('processMessage');
        $method->setAccessible(true);
        $method->invoke($notification, '');

        $this->assertSame('', $notification->getOutputMessage(''));
        $this->assertSame([], $notification->getOutputVariables());
    }

    /**
     * Test that processMessage replaces simple registered variables.
     *
     * Ensures that variables like %name% and %age% are correctly replaced
     * within the message template, and that the replaced variables are stored
     * in getOutputVariables().
     */
    public function testProcessMessageReplacesSimpleVariables()
    {
        $notification = NotificationFactory::getCustom();
        $notification->registerVariables([
            '%name%' => 'Sara',
            '%age%'  => 28,
        ]);

        $template = 'My name is %name% and I am %age% years old.';

        $reflection = new \ReflectionClass($notification);
        $method     = $reflection->getMethod('processMessage');
        $method->setAccessible(true);
        $method->invoke($notification, $template);

        $this->assertSame(
            'My name is Sara and I am 28 years old.',
            $notification->getOutputMessage($template)
        );

        $this->assertEquals(
            ['name' => 'Sara', 'age' => '28'],
            $notification->getOutputVariables()
        );
    }

    /**
     * Test that processMessage replaces WooCommerce order meta variables.
     *
     * Ensures that placeholders like %order_meta_tracking_number%
     * are replaced with the correct order meta values, and that
     * the variables are properly stored in getOutputVariables().
     */
    public function testProcessMessageReplacesOrderMeta()
    {
        $orderId = $this->factory()->post->create(['post_type' => 'shop_order']);
        $order   = wc_get_order($orderId);
        $order->update_meta_data('tracking_number', 'XYZ123');
        $order->save();

        $notification = NotificationFactory::getWooCommerceOrder($order);

        $template = 'Your tracking number is %order_meta_tracking_number%';

        $reflection = new \ReflectionClass($notification);
        $method     = $reflection->getMethod('processMessage');
        $method->setAccessible(true);
        $method->invoke($notification, $template);

        $this->assertSame(
            'Your tracking number is XYZ123',
            $notification->getOutputMessage($template)
        );

        $this->assertEquals(
            ['order_meta_tracking_number' => 'XYZ123'],
            $notification->getOutputVariables()
        );
    }
}
