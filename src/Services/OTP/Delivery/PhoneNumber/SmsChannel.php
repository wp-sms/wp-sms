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

    public function send(string $to, string $message, array $context = [])
    {
        //TODO: Implement SMS sending
        // $result = Sms::send([
        //     'to'  => $to,
        //     'msg' => $message,
        // ]);
        error_log("Message sent to {$to}: {$message}");
        return true;
    }
}
