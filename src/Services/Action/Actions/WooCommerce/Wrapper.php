<?php

namespace WPSmsTwoWay\Services\Action\Actions\WooCommerce;

use WPSmsTwoWay\Services\Action\Actions\AbstractClassWrapper;
use WPSmsTwoWay\Services\Action\Exceptions\ActionException;
use WPSmsTwoWay\Core\Helper;
use WPSmsTwoWay\Models\IncomingMessage;

class Wrapper extends AbstractClassWrapper
{
    public const NAME        = 'woo-commerce';
    public const DESCRIPTION = 'WooCommerce actions';

    /**
     * @inheritDoc
     */
    public static function checkRequirements():bool
    {
        if (class_exists('WooCommerce')) {
            return true;
        }
        return false;
    }

    /**========================================================================
     *                           Helper Functions
     *========================================================================**/

    /**
     * Check if a WooCommerce order exists the with provided id
     *
     * @param WPSmsTwoWay\Models\IncomingMessage $message
     * @return \WC_Order
     * @throws WPSmsTwoWay\Services\Action\Exceptions\ActionException if order does not exist
     */
    public static function validateOrder(int $orderId)
    {
        $order   = wc_get_order($orderId);

        if (!$order) {
            throw new ActionException('Order id is not valid');
        }

        return $order;
    }

    /**
     * Validate sender's phone number
     *
     * @param integer $orderId
     * @param IncomingMessage $message
     * @throws ActionException
     * @return void
     */
    public static function validateNumber(int $orderId, IncomingMessage $message)
    {
        $orderNumber  = Helper::getCustomerMobileNumber($orderId);
        $senderNumber = $message->sender_number;

        if (!Helper::compareSenderAndOrderPhones($senderNumber, $orderNumber)) {
            throw new ActionException('Sender phone number is different than order phone number.');
        }

        return $senderNumber;
    }
}
