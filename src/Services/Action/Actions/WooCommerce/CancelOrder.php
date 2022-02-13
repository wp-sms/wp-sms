<?php

namespace WPSmsTwoWay\Services\Action\Actions\WooCommerce;

use WPSmsTwoWay\Services\Action\Actions\AbstractAction;

class CancelOrder extends AbstractAction
{
    /**
     * @var string
     */
    protected $description = 'Cancel Order';

    /**
     * @var array
     */
    protected $callbackParams = [
        0 => ['name' =>'order-id', 'type' => 'int', 'example' => 'order-id']
    ];

    /**
     * @var array
     */
    protected $responseParams;

    /**
     * Action's callback
     *
     * @param WPSmsTwoWay\Models\IncomingMessage $message
     * @return void
     */
    protected function callback($message)
    {
        $orderId      = (int)($message->command_args[0] ?? null);
        $order        = Wrapper::validateOrder($orderId);
        $senderNumber = Wrapper::validateNumber($orderId, $message);

        $orderCanBeCanceled = $order->has_status(apply_filters('wpsms_tw_valid_order_statuses_for_cancel', ['pending', 'failed'], $order));

        if (!$orderCanBeCanceled) {
            throw new ActionException('Order cannot be canceled');
        }

        // Let us cancel the order
        $order->update_status('cancelled', __('Order cancelled by customer.', 'woocommerce'));
    }
}
