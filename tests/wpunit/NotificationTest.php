<?php

use WP_SMS\Notification\NotificationFactory;

class NotificationTest extends \Codeception\TestCase\WPTestCase
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

    // Tests
    public function test_it_works()
    {
        $post = static::factory()->post->create_and_get();

        $this->assertInstanceOf(\WP_Post::class, $post);
    }

    public function testPrintVariables()
    {
        $notification = NotificationFactory::getCustom();
        $notification->registerVariables([
            '%age%'  => 20,
            '%name%' => 'John'
        ]);

        $this->assertStringContainsString(
            $notification->printVariables(),
            "<code>%age%</code> <code>%name%</code>"
        );
    }


    public function testCustomOutputMessage()
    {
        $notification = NotificationFactory::getCustom();
        $notification->registerVariables([
            '%age%'  => 20,
            '%name%' => 'John'
        ]);

        $this->assertStringContainsString(
            $notification->getOutputMessage('Hello, %name%, %age%'),
            "Hello, John, 20"
        );
    }

    public function testPostOutputMessage()
    {
        $postId       = static::factory()->post->create();
        $notification = NotificationFactory::getPost($postId);

        $this->assertStringContainsString(
            $notification->getOutputMessage('Post ID #%post_id%'),
            "Post ID #{$postId}"
        );
    }

    public function testCommentOutputMessage()
    {
        $commentId    = static::factory()->comment->create();
        $notification = NotificationFactory::getComment($commentId);

        $this->assertStringContainsString(
            $notification->getOutputMessage('Comment ID #%comment_id%'),
            "Comment ID #{$commentId}"
        );
    }

    public function testUserOutputMessage()
    {
        $userId       = static::factory()->user->create();
        $notification = NotificationFactory::getUser($userId);

        $this->assertStringContainsString(
            $notification->getOutputMessage('User ID #%user_id%'),
            "User ID #{$userId}"
        );
    }

    public function testSubscriberOutputMessage()
    {
        $subscriber   = WPSms()->newsletter()::addSubscriber('Vernon C. Cahill', '6506896529');
        $notification = NotificationFactory::getSubscriber($subscriber['id']);

        $this->assertStringContainsString(
            $notification->getOutputMessage('Name: %subscriber_name%, Mobile: %subscriber_mobile%'),
            "Name: Vernon C. Cahill, Mobile: 6506896529"
        );
    }

    public function testCouponNotificationOutput()
    {
        $coupon = new WC_Coupon();
        $coupon->set_code('FDSGFGFDG');
        $coupon->set_amount('20');
        $coupon->save();

        $notification = NotificationFactory::getWooCommerceCoupon($coupon);

        $this->assertStringContainsString(
            $notification->getOutputMessage('Coupon Code : %coupon_code% , Coupon Amount : %coupon_amount%'),
            "Coupon Code : {$coupon->get_code()} , Coupon Amount : {$coupon->get_amount()}"
        );
    }

    public function testCustomerNotificationOutput()
    {
        $customer = new WC_Customer();
        $customer->set_id('0');
        $customer->set_email('test@test.com');
        $customer->set_username('toptop');
        $customer->set_first_name('John');
        $customer->set_last_name('Smith');
        $customer->set_address('Ottendorf-Okrilla, Freistaat Sachsen(SN), 01455');
        $customer->save();

        $notification = NotificationFactory::getWooCommerceCustomer($customer);

        $this->assertStringContainsString(
            $notification->getOutputMessage('Customer Id: %customer_id%, Customer Email: %customer_email%, Customer Username: %customer_username%, Customer Firstname: %customer_first_name%, Customer Lastname: %customer_last_name%, Customer Address: %customer_address%'),
            "Customer Id: {$customer->get_id()}, Customer Email: {$customer->get_email()}, Customer Username: {$customer->get_username()}, Customer Firstname: {$customer->get_first_name()}, Customer Lastname: {$customer->get_last_name()}, Customer Address: {$customer->get_address()}"
        );
    }
}
