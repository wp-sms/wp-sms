<?php

namespace WSms\Integration\Line\FlexTemplates;

defined('ABSPATH') || exit;

class Coupon
{
    /**
     * Generate a coupon Flex Message bubble.
     *
     * @param array $data {
     *     @type string $code
     *     @type string $discount     e.g. "20%", "$10"
     *     @type string $description
     *     @type string $expires_at
     *     @type string $redeem_url
     *     @type string $store_name
     * }
     */
    public static function build(array $data): array
    {
        $code = $data['code'] ?? '';
        $discount = $data['discount'] ?? '';
        $description = $data['description'] ?? '';
        $expiresAt = $data['expires_at'] ?? '';
        $redeemUrl = $data['redeem_url'] ?? '';
        $storeName = $data['store_name'] ?? '';

        $bodyContents = [
            [
                'type' => 'text',
                'text' => 'COUPON',
                'color' => '#FF6B6B',
                'size' => 'sm',
                'weight' => 'bold',
            ],
            [
                'type' => 'text',
                'text' => $discount . ' OFF',
                'weight' => 'bold',
                'size' => 'xxl',
                'margin' => 'md',
            ],
        ];

        if (!empty($storeName)) {
            $bodyContents[] = [
                'type' => 'text',
                'text' => $storeName,
                'size' => 'sm',
                'color' => '#999999',
            ];
        }

        if (!empty($description)) {
            $bodyContents[] = [
                'type' => 'text',
                'text' => $description,
                'size' => 'sm',
                'color' => '#666666',
                'margin' => 'lg',
                'wrap' => true,
            ];
        }

        $bodyContents[] = [
            'type' => 'separator',
            'margin' => 'xxl',
        ];

        $bodyContents[] = [
            'type' => 'box',
            'layout' => 'vertical',
            'margin' => 'xxl',
            'contents' => [
                [
                    'type' => 'text',
                    'text' => 'CODE',
                    'size' => 'xs',
                    'color' => '#aaaaaa',
                ],
                [
                    'type' => 'text',
                    'text' => $code,
                    'size' => 'xl',
                    'weight' => 'bold',
                    'margin' => 'sm',
                ],
            ],
        ];

        if (!empty($expiresAt)) {
            $bodyContents[] = [
                'type' => 'text',
                'text' => 'Expires: ' . $expiresAt,
                'size' => 'xs',
                'color' => '#aaaaaa',
                'margin' => 'md',
            ];
        }

        $bubble = [
            'type' => 'bubble',
            'body' => [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => $bodyContents,
            ],
        ];

        if (!empty($redeemUrl)) {
            $bubble['footer'] = FlexHelper::footer($redeemUrl, 'Redeem Now', '#FF6B6B');
        }

        return $bubble;
    }

    public static function altText(array $data): string
    {
        $discount = $data['discount'] ?? '';
        $code = $data['code'] ?? '';

        return "{$discount} off with code {$code}";
    }
}
