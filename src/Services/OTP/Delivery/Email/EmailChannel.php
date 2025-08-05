<?php

namespace WP_SMS\Services\OTP\Delivery\Email;

use WP_SMS\Services\OTP\Contracts\Interfaces\DeliveryChannelInterface;

class EmailChannel implements DeliveryChannelInterface
{
    public function getKey(): string
    {
        return 'email';
    }

    public function send(string $to, string $message, array $context = []): bool
    {
        $subject = $context['subject'] ?? __('Your Login Code or Link', 'wp-sms');
        $headers = ['Content-Type: text/plain; charset=UTF-8'];

        return wp_mail($to, $subject, $message, $headers);
    }
}
