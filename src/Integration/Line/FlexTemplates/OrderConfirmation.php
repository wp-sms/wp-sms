<?php

namespace WSms\Integration\Line\FlexTemplates;

defined('ABSPATH') || exit;

class OrderConfirmation
{
    /**
     * Generate an order confirmation Flex Message bubble.
     *
     * @param array $data {
     *     @type string|int $order_id
     *     @type array      $items     [ ['name' => ..., 'quantity' => ..., 'price' => ...], ... ]
     *     @type string     $total
     *     @type string     $currency
     *     @type string     $order_url
     *     @type string     $store_name
     * }
     */
    public static function build(array $data): array
    {
        $orderId = $data['order_id'] ?? '';
        $items = $data['items'] ?? [];
        $total = $data['total'] ?? '0';
        $currency = $data['currency'] ?? 'JPY';
        $orderUrl = $data['order_url'] ?? '';
        $storeName = $data['store_name'] ?? '';

        $itemContents = [];

        foreach (array_slice($items, 0, 8) as $item) {
            $itemContents[] = [
                'type' => 'box',
                'layout' => 'horizontal',
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => ($item['name'] ?? 'Item') . ' x' . ($item['quantity'] ?? 1),
                        'size' => 'sm',
                        'color' => '#555555',
                        'flex' => 0,
                    ],
                    [
                        'type' => 'text',
                        'text' => $currency . ' ' . ($item['price'] ?? '0'),
                        'size' => 'sm',
                        'color' => '#111111',
                        'align' => 'end',
                    ],
                ],
            ];
        }

        $bubble = [
            'type' => 'bubble',
            'body' => [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => 'ORDER CONFIRMATION',
                        'weight' => 'bold',
                        'color' => '#1DB446',
                        'size' => 'sm',
                    ],
                    [
                        'type' => 'text',
                        'text' => $storeName ?: 'Order #' . $orderId,
                        'weight' => 'bold',
                        'size' => 'xxl',
                        'margin' => 'md',
                    ],
                    [
                        'type' => 'text',
                        'text' => 'Order #' . $orderId,
                        'size' => 'xs',
                        'color' => '#aaaaaa',
                        'wrap' => true,
                    ],
                    [
                        'type' => 'separator',
                        'margin' => 'xxl',
                    ],
                    [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'margin' => 'xxl',
                        'spacing' => 'sm',
                        'contents' => $itemContents,
                    ],
                    [
                        'type' => 'separator',
                        'margin' => 'xxl',
                    ],
                    [
                        'type' => 'box',
                        'layout' => 'horizontal',
                        'margin' => 'xxl',
                        'contents' => [
                            [
                                'type' => 'text',
                                'text' => 'TOTAL',
                                'size' => 'sm',
                                'color' => '#555555',
                            ],
                            [
                                'type' => 'text',
                                'text' => $currency . ' ' . $total,
                                'size' => 'sm',
                                'color' => '#111111',
                                'align' => 'end',
                                'weight' => 'bold',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        if (!empty($orderUrl)) {
            $bubble['footer'] = FlexHelper::footer($orderUrl, 'View Order');
        }

        return $bubble;
    }

    public static function altText(array $data): string
    {
        $orderId = $data['order_id'] ?? '';
        $total = $data['total'] ?? '0';
        $currency = $data['currency'] ?? 'JPY';

        return "Order #{$orderId} confirmed - {$currency} {$total}";
    }
}
