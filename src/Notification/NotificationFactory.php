<?php

namespace WP_SMS\Notification;

use WP_SMS\Notification\Handler\CustomNotification;
use WP_SMS\Notification\Handler\WooCommerceOrderNotification;
use WP_SMS\Notification\Handler\WooCommerceProductNotification;
use WP_SMS\Notification\Handler\WordPressPostNotification;
use WP_SMS\Notification\Handler\WordPresUserNotification;

class NotificationFactory
{
    /**
     * @param $orderId
     * @return WooCommerceOrderNotification
     */
    public static function getWooCommerceOrder($orderId = false)
    {
        return new WooCommerceOrderNotification($orderId);
    }

    /**
     * @param $productId
     * @return WooCommerceProductNotification
     */
    public static function getWooCommerceProduct($productId = false)
    {
        return new WooCommerceProductNotification($productId);
    }

    /**
     * @param $postId
     * @return WordPressPostNotification
     */
    public static function getPost($postId)
    {
        return new WordPressPostNotification($postId);
    }

    /**
     * @param $commentId
     * @return WordPresUserNotification
     */
    public static function getUser($commentId)
    {
        return new WordPresUserNotification($commentId);
    }

    /**
     * @return CustomNotification
     */
    public static function getCustom()
    {
        return new CustomNotification();
    }
}