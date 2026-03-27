<?php

namespace WSms\Integration\Line\FlexTemplates;

defined('ABSPATH') || exit;

class DeliveryConfirmation
{
    /**
     * Generate a delivery confirmation Flex Message bubble.
     *
     * @param array $data {
     *     @type string|int $order_id
     *     @type string     $review_url
     * }
     */
    public static function build(array $data): array
    {
        $orderId = $data['order_id'] ?? '';
        $reviewUrl = $data['review_url'] ?? '';

        $bubble = [
            'type' => 'bubble',
            'body' => [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => 'DELIVERED',
                        'weight' => 'bold',
                        'color' => '#1DB446',
                        'size' => 'sm',
                    ],
                    [
                        'type' => 'text',
                        'text' => 'Your order has arrived!',
                        'weight' => 'bold',
                        'size' => 'xl',
                        'margin' => 'md',
                    ],
                    [
                        'type' => 'text',
                        'text' => 'Order #' . $orderId . ' has been delivered.',
                        'size' => 'sm',
                        'color' => '#666666',
                        'margin' => 'md',
                        'wrap' => true,
                    ],
                ],
            ],
        ];

        if (!empty($reviewUrl)) {
            $bubble['footer'] = FlexHelper::footer($reviewUrl, 'Leave a Review', '#FF6B6B');
        }

        return $bubble;
    }

    public static function altText(array $data): string
    {
        $orderId = $data['order_id'] ?? '';

        return "Order #{$orderId} delivered!";
    }
}
