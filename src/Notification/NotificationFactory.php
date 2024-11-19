<?php

namespace WP_SMS\Notification;

use WP_SMS\Notification\Handler\CustomNotification;
use WP_SMS\Notification\Handler\DefaultNotification;
use WP_SMS\Notification\Handler\ForminatorNotification;
use WP_SMS\Notification\Handler\SubscriberNotification;
use WP_SMS\Notification\Handler\WooCommerceOrderNotification;
use WP_SMS\Notification\Handler\WooCommerceProductNotification;
use WP_SMS\Notification\Handler\WordPressCommentNotification;
use WP_SMS\Notification\Handler\WordPressPostNotification;
use WP_SMS\Notification\Handler\WordPressUserNotification;
use WP_SMS\Notification\Handler\WooCommerceCouponNotification;
use WP_SMS\Notification\Handler\WooCommerceCustomerNotification;
use WP_SMS\Notification\Handler\AwesomeSupportTicketNotification;
use WP_SMS\Notification\Handler\FormidableNotification;
use WP_SMS\Notification\Handler\WooCommerceAdminOrderNotification;

class NotificationFactory
{
    public static function getHandler($handlerName = false, $handlerId = false)
    {
        if ($handlerName) {
            $className = 'WP_SMS\Notification\Handler\\' . $handlerName;

            if (class_exists($className)) {
                return new $className($handlerId);
            }
        }

        return new DefaultNotification();
    }

    /**
     * @param $orderId
     * @return WooCommerceOrderNotification
     */
    public static function getWooCommerceOrder($orderId = false)
    {
        return new WooCommerceOrderNotification($orderId);
    }

    /**
     * @param $orderId
     * @return WooCommerceAdminOrderNotification
     */
    public static function getWooCommerceAdminOrder($orderId = false)
    {
        return new WooCommerceAdminOrderNotification($orderId);
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
     * @param $couponId
     * @return WooCommerceCouponNotification
     */
    public static function getWooCommerceCoupon($couponId = false)
    {
        return new WooCommerceCouponNotification($couponId);
    }

    /**
     * @param $customerId
     * @return WooCommerceCustomerNotification
     */
    public static function getWooCommerceCustomer($customerId = false)
    {
        return new WooCommerceCustomerNotification($customerId);
    }

    /**
     * @param $postId
     * @return WordPressPostNotification
     */
    public static function getPost($postId = false)
    {
        return new WordPressPostNotification($postId);
    }

    /**
     * @param $userId
     * @return WordPressUserNotification
     */
    public static function getUser($userId = false)
    {
        return new WordPressUserNotification($userId);
    }

    /**
     * @param $userId
     * @return AwesomeSupportTicketNotification
     */
    public static function getAwesomeSupportTicket($ticketId = false)
    {
        return new AwesomeSupportTicketNotification($ticketId);
    }

    /**
     * @param $commentId
     * @return WordPressCommentNotification
     */
    public static function getComment($commentId = false)
    {
        return new WordPressCommentNotification($commentId);
    }

    /**
     * @param $subscriberId
     * @return SubscriberNotification
     */
    public static function getSubscriber($subscriberId = false)
    {
        return new SubscriberNotification($subscriberId);
    }

    /**
     * getForminator function
     *
     * @param [type] $form_id
     * @param [type] $data
     * @return ForminatorNotification
     */
    public static function getForminator($form_id, $data = [])
    {
        return new ForminatorNotification($form_id, $data);
    }

    /**
     * @return CustomNotification
     */
    public static function getCustom()
    {
        return new CustomNotification();
    }

    /**
     * @return FormidableNotification
     */
    public static function getFormidable($form, $data = [])
    {
        return new FormidableNotification($form, $data);
    }

}