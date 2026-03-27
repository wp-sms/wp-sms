<?php

namespace WSms\Integration\Line\FlexTemplates;

defined('ABSPATH') || exit;

class ShippingUpdate
{
    /**
     * Generate a shipping update Flex Message bubble.
     *
     * @param array $data {
     *     @type string|int $order_id
     *     @type string     $carrier
     *     @type string     $tracking_number
     *     @type string     $tracking_url
     *     @type string     $status       e.g. "Shipped", "Out for Delivery"
     * }
     */
    public static function build(array $data): array
    {
        $orderId = $data['order_id'] ?? '';
        $carrier = $data['carrier'] ?? '';
        $trackingNumber = $data['tracking_number'] ?? '';
        $trackingUrl = $data['tracking_url'] ?? '';
        $status = $data['status'] ?? 'Shipped';

        $infoItems = [
            FlexHelper::baselineRow('Order', '#' . $orderId),
        ];

        if (!empty($carrier)) {
            $infoItems[] = FlexHelper::baselineRow('Carrier', $carrier);
        }

        if (!empty($trackingNumber)) {
            $infoItems[] = FlexHelper::baselineRow('Tracking', $trackingNumber);
        }

        $bubble = [
            'type' => 'bubble',
            'body' => [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => mb_strtoupper($status),
                        'weight' => 'bold',
                        'color' => '#1DB446',
                        'size' => 'sm',
                    ],
                    [
                        'type' => 'text',
                        'text' => 'Shipping Update',
                        'weight' => 'bold',
                        'size' => 'xl',
                        'margin' => 'md',
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
                        'contents' => $infoItems,
                    ],
                ],
            ],
        ];

        if (!empty($trackingUrl)) {
            $bubble['footer'] = FlexHelper::footer($trackingUrl, 'Track Package');
        }

        return $bubble;
    }

    public static function altText(array $data): string
    {
        $orderId = $data['order_id'] ?? '';
        $status = $data['status'] ?? 'Shipped';

        return "Order #{$orderId} - {$status}";
    }
}
