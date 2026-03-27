<?php

namespace WSms\Integration\Line\FlexTemplates;

defined('ABSPATH') || exit;

class PaymentReminder
{
    /**
     * Generate a payment reminder Flex Message bubble.
     *
     * @param array $data {
     *     @type string|int $order_id
     *     @type string     $total
     *     @type string     $currency
     *     @type string     $due_date
     *     @type string     $payment_url
     * }
     */
    public static function build(array $data): array
    {
        $orderId = $data['order_id'] ?? '';
        $total = $data['total'] ?? '0';
        $currency = $data['currency'] ?? 'JPY';
        $dueDate = $data['due_date'] ?? '';
        $paymentUrl = $data['payment_url'] ?? '';

        $infoItems = [
            FlexHelper::baselineRow('Order', '#' . $orderId),
            FlexHelper::baselineRow('Amount', $currency . ' ' . $total, '#111111', true),
        ];

        if (!empty($dueDate)) {
            $infoItems[] = FlexHelper::baselineRow('Due', $dueDate, '#FF6B6B', true);
        }

        $bubble = [
            'type' => 'bubble',
            'body' => [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => 'PAYMENT REMINDER',
                        'weight' => 'bold',
                        'color' => '#FF6B6B',
                        'size' => 'sm',
                    ],
                    [
                        'type' => 'text',
                        'text' => 'Payment pending',
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

        if (!empty($paymentUrl)) {
            $bubble['footer'] = FlexHelper::footer($paymentUrl, 'Pay Now', '#FF6B6B');
        }

        return $bubble;
    }

    public static function altText(array $data): string
    {
        $orderId = $data['order_id'] ?? '';
        $total = $data['total'] ?? '0';
        $currency = $data['currency'] ?? 'JPY';

        return "Payment reminder: Order #{$orderId} - {$currency} {$total}";
    }
}
