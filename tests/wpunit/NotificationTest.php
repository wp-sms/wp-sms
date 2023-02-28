<?php

use WP_SMS\Notification\NotificationFactory;
use WC_Coupon;



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
        $subscriber   = WPSms()->newsletter()::getSubscriber(1);
        $notification = NotificationFactory::getSubscriber($subscriber->ID);

        $this->assertStringContainsString(
            $notification->getOutputMessage('Name: %subscriber_name%, Mobile: %subscriber_mobile%'),
            "Name: John, Mobile: 0123456789"
        );
    }

    public function testCouponNotificationOutput()
    {
        $coupon       = new WC_Coupon();
        $notification = NotificationFactory::getWooCommerceCoupon($coupon);

        $this->assertStringContainsString(
            $notification->getOutputMessage('Coupon Code : %coupon_code% , Coupon Amount : %coupon_code%')
            , "Coupon Code : {$coupon->get_code()} , Coupon Amount : {$coupon->get_amount()}"
        );
    }
}
