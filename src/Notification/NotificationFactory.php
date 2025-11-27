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
use WP_SMS\Notification\Handler\OtpNotification;
use WP_SMS\Notification\Handler\ContactForm7Notification;
use WP_SMS\Notification\Handler\BuddyPress\BuddyPressWelcomeNotification;
use WP_SMS\Notification\Handler\BuddyPress\BuddyPressMentionNotification;
use WP_SMS\Notification\Handler\BuddyPress\BuddyPressPrivateMessageNotification;
use WP_SMS\Notification\Handler\BuddyPress\BuddyPressUserCommentsNotification;
use WP_SMS\Notification\Handler\GravityFormsNotification;
use WP_SMS\Notification\Handler\QuformNotification;
use WP_SMS\Notification\Handler\EasyDigitalDownloadsNotification;
use WP_SMS\Notification\Handler\WPJobManagerNotification;

if (!defined('ABSPATH')) exit;

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
    public static function getWooCommerceOrder($orderId = false, $wooData = [])
    {
        return new WooCommerceOrderNotification($orderId, $wooData);
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

    /**
     * @return OtpNotification
     */
    public static function getOtp($phoneNumber = false, $code = false)
    {
        return new OtpNotification($phoneNumber, $code);
    }

    /**
     * @return ContactForm7Notification
     */
    public static function getContactForm7($data = [])
    {
        return new ContactForm7Notification($data);
    }

    /**
     * @return BuddyPressWelcomeNotification
     */
    public static function getBuddyPressWelcome($user = false)
    {
        return new BuddyPressWelcomeNotification($user);
    }

    /**
     * @return BuddyPressMentionNotification
     */
    public static function getBuddyPressMention($bpData = [])
    {
        return new BuddyPressMentionNotification($bpData);
    }

    /**
     * @return BuddyPressPrivateMessageNotification
     */
    public static function getBuddyPressPrivateMessage($bpData = [])
    {
        return new BuddyPressPrivateMessageNotification($bpData);
    }

    /**
     * @return BuddyPressUserCommentsNotification
     */
    public static function getBuddyPressUserComments($activity = false, $comment_id = false)
    {
        return new BuddyPressUserCommentsNotification($activity, $comment_id);
    }

    /**
     * @return GravityFormsNotification
     */
    public static function getGravityForms($gformData = [])
    {
        return new GravityFormsNotification($gformData);
    }

    /**
     * @return QuformNotification
     */
    public static function getQuform($qfData = [])
    {
        return new QuformNotification($qfData);
    }

    /**
     * @return EasyDigitalDownloadsNotification
     */
    public static function getEasyDigitalDownloads($eddData = [])
    {
        return new EasyDigitalDownloadsNotification($eddData);
    }

    /**
     * @return WPJobManagerNotification
     */
    public static function getWPJobManager($jobData = [])
    {
        return new WPJobManagerNotification($jobData);
    }
}