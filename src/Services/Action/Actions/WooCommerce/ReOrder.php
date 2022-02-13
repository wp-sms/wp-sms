<?php

namespace WPSmsTwoWay\Services\Action\Actions\WooCommerce;

use WPSmsTwoWay\Services\Action\Actions\AbstractAction;

class ReOrder extends AbstractAction
{
    /**
     * @var string
     */
    protected $description = 'Re Order';

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
        $orderData    = $order->get_base_data();

        $senderNumber = Wrapper::validateNumber($orderId, $message);

        $newOrder = wc_create_order();

        foreach ($order->get_items() as $orderItem) {
            $newOrder->add_product($orderItem->get_product(), $orderItem->get_quantity());
        }
        foreach ($order->get_coupon_codes() as $coupon_code) {
            $newOrder->add_coupon($coupon_code);
        }
        foreach ($orderData['billing'] as $key => $value) {
            $callback = [$newOrder,"set_billing_{$key}"];
            call_user_func($callback, $value);
        }
        foreach ($orderData['shipping'] as $key => $value) {
            $callback = [$newOrder,"set_shipping_{$key}"];
            call_user_func($callback, $value);
        }

        $newOrder->set_customer_id($order->get_customer_id());
        $newOrder->calculate_shipping();
        $newOrder->calculate_totals();
        $newOrder->save();
    }
}
