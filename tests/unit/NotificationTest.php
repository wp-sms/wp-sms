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
        $variables = [
            '%age%'  => $this->faker->numberBetween(18, 60),
            '%name%' => $this->faker->firstName,
        ];
        $notification->registerVariables($variables);

        $output = $notification->printVariables();

        // Check that all keys and values exist in the output
        foreach ($variables as $key => $value) {
            $this->assertStringContainsString($key, $output, "Key {$key} is missing in the output.");
            $this->assertStringContainsString((string) $value, $output, "Value {$value} is missing in the output.");
        }
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

    /**
     * Test notification getVariables returns registered variables.
     */
    public function testNotificationGetVariablesReturnsRegisteredVariables()
    {
        $notification = NotificationFactory::getCustom();
        $variables    = [
            '%test_var%' => 'Test Value',
        ];
        $notification->registerVariables($variables);

        $result = $notification->getVariables();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('%test_var%', $result);
    }

    /**
     * Test custom notification with multiple variables.
     */
    public function testCustomNotificationWithMultipleVariables()
    {
        $notification = NotificationFactory::getCustom();
        $firstName    = $this->faker->firstName;
        $lastName     = $this->faker->lastName;
        $email        = $this->faker->email;

        $notification->registerVariables([
            '%first_name%' => $firstName,
            '%last_name%'  => $lastName,
            '%email%'      => $email,
        ]);

        $message  = 'Hello %first_name% %last_name%, your email is %email%';
        $expected = "Hello {$firstName} {$lastName}, your email is {$email}";

        $this->assertEquals($expected, $notification->getOutputMessage($message));
    }

    /**
     * Test notification with non-existent variable.
     */
    public function testNotificationWithNonExistentVariable()
    {
        $notification = NotificationFactory::getCustom();
        $notification->registerVariables([
            '%name%' => 'John',
        ]);

        // %unknown% is not registered, should remain as-is
        $message = 'Hello %name%, your ID is %unknown%';
        $result  = $notification->getOutputMessage($message);

        $this->assertStringContainsString('John', $result);
        $this->assertStringContainsString('%unknown%', $result);
    }

    /**
     * Test post notification has post title variable.
     */
    public function testPostNotificationHasPostTitleVariable()
    {
        $postTitle = $this->faker->sentence;
        $postId    = $this->factory()->post->create(['post_title' => $postTitle]);

        $notification = NotificationFactory::getPost($postId);
        $result       = $notification->getOutputMessage('Title: %post_title%');

        $this->assertStringContainsString($postTitle, $result);
    }

    /**
     * Test post notification has post content variable.
     */
    public function testPostNotificationHasPostContentVariable()
    {
        // Use a short content that won't be trimmed
        $postContent = 'This is a test content';
        $postId      = $this->factory()->post->create(['post_content' => $postContent]);

        $notification = NotificationFactory::getPost($postId);
        $result       = $notification->getOutputMessage('Content: %post_content%');

        // Content is trimmed by wp_trim_words, so just check it's not the placeholder
        $this->assertStringNotContainsString('%post_content%', $result);
        $this->assertStringContainsString('Content:', $result);
    }

    /**
     * Test post notification has post URL variable.
     */
    public function testPostNotificationHasPostUrlVariable()
    {
        $postId = $this->factory()->post->create();

        $notification = NotificationFactory::getPost($postId);
        $result       = $notification->getOutputMessage('URL: %post_url%');

        $this->assertStringContainsString(get_permalink($postId), $result);
    }

    /**
     * Test user notification has user email variable.
     */
    public function testUserNotificationHasUserEmailVariable()
    {
        $userEmail = $this->faker->email;
        $userId    = $this->factory()->user->create(['user_email' => $userEmail]);

        $notification = NotificationFactory::getUser($userId);
        $result       = $notification->getOutputMessage('Email: %user_email%');

        $this->assertStringContainsString($userEmail, $result);
    }

    /**
     * Test user notification has display name variable.
     */
    public function testUserNotificationHasDisplayNameVariable()
    {
        $displayName = $this->faker->name;
        $userId      = $this->factory()->user->create(['display_name' => $displayName]);

        $notification = NotificationFactory::getUser($userId);
        $result       = $notification->getOutputMessage('Name: %display_name%');

        $this->assertStringContainsString($displayName, $result);
    }

    /**
     * Test comment notification has comment author variable.
     */
    public function testCommentNotificationHasCommentAuthorVariable()
    {
        $authorName = $this->faker->name;
        $commentId  = $this->factory()->comment->create(['comment_author' => $authorName]);

        $notification = NotificationFactory::getComment($commentId);
        $result       = $notification->getOutputMessage('Author: %comment_author%');

        $this->assertStringContainsString($authorName, $result);
    }

    /**
     * Test comment notification has comment content variable.
     */
    public function testCommentNotificationHasCommentContentVariable()
    {
        $content   = $this->faker->sentence;
        $commentId = $this->factory()->comment->create(['comment_content' => $content]);

        $notification = NotificationFactory::getComment($commentId);
        $result       = $notification->getOutputMessage('Comment: %comment_content%');

        $this->assertStringContainsString($content, $result);
    }

    /**
     * Test OTP notification has code variable.
     */
    public function testOtpNotificationHasCodeVariable()
    {
        $phoneNumber  = '+12025551234';
        $code         = '123456';
        $notification = NotificationFactory::getOtp($phoneNumber, $code);

        // OTP uses %code% or %otp% variable
        $result = $notification->getOutputMessage('Your code is %code%');

        $this->assertStringContainsString($code, $result);
    }

    /**
     * Test WooCommerce order notification has order ID variable.
     */
    public function testWooCommerceOrderNotificationHasOrderIdVariable()
    {
        $orderId = $this->factory()->post->create(['post_type' => 'shop_order']);
        $order   = wc_get_order($orderId);

        $notification = NotificationFactory::getWooCommerceOrder($order);
        $result       = $notification->getOutputMessage('Order: #%order_id%');

        $this->assertStringContainsString((string) $orderId, $result);
    }

    /**
     * Test WooCommerce order notification has order status variable.
     */
    public function testWooCommerceOrderNotificationHasOrderStatusVariable()
    {
        $orderId = $this->factory()->post->create(['post_type' => 'shop_order']);
        $order   = wc_get_order($orderId);
        $order->set_status('processing');
        $order->save();

        $notification = NotificationFactory::getWooCommerceOrder($order);
        $result       = $notification->getOutputMessage('Status: %status%');

        $this->assertStringContainsString('processing', strtolower($result));
    }

    /**
     * Test WooCommerce product notification has product title variable.
     */
    public function testWooCommerceProductNotificationHasProductTitleVariable()
    {
        $productName = $this->faker->words(3, true);
        $productId   = $this->factory()->post->create([
            'post_type'  => 'product',
            'post_title' => $productName,
        ]);

        $notification = NotificationFactory::getWooCommerceProduct($productId);
        // Product notification uses %product_title% not %product_name%
        $result       = $notification->getOutputMessage('Product: %product_title%');

        $this->assertStringContainsString($productName, $result);
    }

    /**
     * Test notification factory getHandler returns default for unknown handler.
     */
    public function testNotificationFactoryGetHandlerReturnsDefaultForUnknown()
    {
        $handler = NotificationFactory::getHandler('NonExistentHandler');

        $this->assertInstanceOf(
            \WP_SMS\Notification\Handler\DefaultNotification::class,
            $handler
        );
    }

    /**
     * Test notification factory getHandler returns correct handler.
     */
    public function testNotificationFactoryGetHandlerReturnsCorrectHandler()
    {
        $handler = NotificationFactory::getHandler('CustomNotification');

        $this->assertInstanceOf(
            \WP_SMS\Notification\Handler\CustomNotification::class,
            $handler
        );
    }

    /**
     * Test getOutputVariables returns empty array before processing.
     */
    public function testGetOutputVariablesReturnsEmptyArrayBeforeProcessing()
    {
        $notification = NotificationFactory::getCustom();

        $this->assertEquals([], $notification->getOutputVariables());
    }

    /**
     * Test notification caches processed message.
     */
    public function testNotificationCachesProcessedMessage()
    {
        $notification = NotificationFactory::getCustom();
        $notification->registerVariables([
            '%name%' => 'John',
        ]);

        $message = 'Hello %name%';

        // Call twice
        $result1 = $notification->getOutputMessage($message);
        $result2 = $notification->getOutputMessage($message);

        $this->assertEquals($result1, $result2);
        $this->assertEquals('Hello John', $result1);
    }

    /**
     * Test notification reprocesses when message changes.
     */
    public function testNotificationReprocessesWhenMessageChanges()
    {
        $notification = NotificationFactory::getCustom();
        $notification->registerVariables([
            '%name%' => 'John',
            '%city%' => 'NYC',
        ]);

        $result1 = $notification->getOutputMessage('Hello %name%');
        $result2 = $notification->getOutputMessage('Hello from %city%');

        $this->assertEquals('Hello John', $result1);
        $this->assertEquals('Hello from NYC', $result2);
    }
}
