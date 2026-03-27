<?php

namespace WSms\Integration\Line\FlexTemplates;

defined('ABSPATH') || exit;

class AbandonedCart
{
    /**
     * Generate an abandoned cart Flex Message carousel.
     *
     * @param array $data {
     *     @type array  $items       [ ['name' => ..., 'price' => ..., 'image' => ..., 'url' => ...], ... ]
     *     @type string $checkout_url
     *     @type string $currency
     * }
     */
    public static function build(array $data): array
    {
        $items = $data['items'] ?? [];
        $checkoutUrl = $data['checkout_url'] ?? '';
        $currency = $data['currency'] ?? 'JPY';

        $bubbles = [];

        // Max 10 bubbles per carousel — reserve last slot for checkout CTA.
        foreach (array_slice($items, 0, 9) as $item) {
            $bubble = [
                'type' => 'bubble',
                'body' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => 'Still in your cart',
                            'color' => '#FF6B6B',
                            'size' => 'xs',
                            'weight' => 'bold',
                        ],
                        [
                            'type' => 'text',
                            'text' => $item['name'] ?? 'Item',
                            'weight' => 'bold',
                            'size' => 'lg',
                            'margin' => 'md',
                            'wrap' => true,
                        ],
                        [
                            'type' => 'text',
                            'text' => $currency . ' ' . ($item['price'] ?? '0'),
                            'size' => 'md',
                            'color' => '#111111',
                            'margin' => 'sm',
                            'weight' => 'bold',
                        ],
                    ],
                ],
            ];

            if (!empty($item['image'])) {
                $bubble['hero'] = FlexHelper::heroImage($item['image']);
            }

            if (!empty($item['url'])) {
                $bubble['footer'] = FlexHelper::footer($item['url'], 'View Item');
            }

            $bubbles[] = $bubble;
        }

        // Add checkout CTA bubble.
        if (!empty($checkoutUrl)) {
            $bubbles[] = [
                'type' => 'bubble',
                'body' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'justifyContent' => 'center',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => 'Complete your purchase',
                            'weight' => 'bold',
                            'size' => 'lg',
                            'wrap' => true,
                            'align' => 'center',
                        ],
                        [
                            'type' => 'button',
                            'style' => 'primary',
                            'color' => '#1DB446',
                            'margin' => 'xl',
                            'action' => [
                                'type' => 'uri',
                                'label' => 'Checkout Now',
                                'uri' => $checkoutUrl,
                            ],
                        ],
                    ],
                ],
            ];
        }

        return [
            'type' => 'carousel',
            'contents' => $bubbles,
        ];
    }

    public static function altText(array $data): string
    {
        $count = count($data['items'] ?? []);

        return "You have {$count} item(s) in your cart";
    }
}
