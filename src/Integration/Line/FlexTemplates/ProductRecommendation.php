<?php

namespace WSms\Integration\Line\FlexTemplates;

defined('ABSPATH') || exit;

class ProductRecommendation
{
    /**
     * Generate a product recommendation Flex Message carousel.
     *
     * @param array $data {
     *     @type array  $products [ ['name' => ..., 'price' => ..., 'image' => ..., 'url' => ...], ... ]
     *     @type string $currency
     *     @type string $heading
     * }
     */
    public static function build(array $data): array
    {
        $products = $data['products'] ?? [];
        $currency = $data['currency'] ?? 'JPY';

        // Max 10 bubbles per carousel.
        $bubbles = [];

        foreach (array_slice($products, 0, 10) as $product) {
            $bubble = [
                'type' => 'bubble',
                'body' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => $product['name'] ?? 'Product',
                            'weight' => 'bold',
                            'size' => 'md',
                            'wrap' => true,
                        ],
                        [
                            'type' => 'text',
                            'text' => $currency . ' ' . ($product['price'] ?? '0'),
                            'size' => 'lg',
                            'color' => '#111111',
                            'margin' => 'sm',
                            'weight' => 'bold',
                        ],
                    ],
                ],
            ];

            if (!empty($product['image'])) {
                $bubble['hero'] = FlexHelper::heroImage($product['image']);
            }

            if (!empty($product['url'])) {
                $bubble['footer'] = FlexHelper::footer($product['url'], 'View Product');
            }

            $bubbles[] = $bubble;
        }

        return [
            'type' => 'carousel',
            'contents' => $bubbles,
        ];
    }

    public static function altText(array $data): string
    {
        $heading = $data['heading'] ?? 'Recommended for you';
        $count = count($data['products'] ?? []);

        return "{$heading} - {$count} products";
    }
}
