<?php

namespace WP_SMS\Services\OTP\Delivery\PhoneNumber;

use WP_SMS\Components\Sms;
use WP_SMS\Services\OTP\Contracts\Interfaces\DeliveryChannelInterface;

class SmsChannel implements DeliveryChannelInterface
{
    public function getKey(): string
    {
        return 'sms';
    }

    public function send(string $to, string $message, array $context = []): bool
    {
        return Sms::send([
            'to'  => $to,
            'msg' => $message,
        ]);
    }
}
