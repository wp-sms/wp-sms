<?php

namespace WSms\Messaging;

use WSms\Messaging\Message\EmailMessage;

defined('ABSPATH') || exit;

class OtpEmailBuilder
{
    public static function build(string $recipient, string $otp, int $expirySeconds): EmailMessage
    {
        $siteName = get_bloginfo('name');
        $subject = sprintf(__('[%s] Your verification code', 'wp-sms'), $siteName);
        $body = sprintf(
            '<p>' . __('Your verification code is:', 'wp-sms') . '</p>'
            . '<p style="font-size:24px;font-weight:bold;letter-spacing:4px;">%s</p>'
            . '<p>' . __('This code expires in %d minutes.', 'wp-sms') . '</p>'
            . '<p>' . __('If you did not request this, please ignore this email.', 'wp-sms') . '</p>',
            esc_html($otp),
            (int) ceil($expirySeconds / 60),
        );

        return new EmailMessage($recipient, $body, $subject, ['Content-Type: text/html; charset=UTF-8']);
    }
}
