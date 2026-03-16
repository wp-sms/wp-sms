<?php

namespace WSms\Verification;

defined('ABSPATH') || exit;

class VerificationMailer
{
    /**
     * Send a verification OTP via email.
     *
     * Shared between VerificationService (standalone) and AccountManager (auth).
     */
    public static function sendOtp(string $email, string $otp, int $expirySeconds): bool
    {
        $siteName = get_bloginfo('name');
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $subject = sprintf(__('[%s] Your verification code', 'wp-sms'), $siteName);
        $message = sprintf(
            '<p>' . __('Your verification code is:', 'wp-sms') . '</p>'
            . '<p style="font-size:24px;font-weight:bold;letter-spacing:4px;">%s</p>'
            . '<p>' . __('This code expires in %d minutes.', 'wp-sms') . '</p>'
            . '<p>' . __('If you did not request this, please ignore this email.', 'wp-sms') . '</p>',
            esc_html($otp),
            (int) ceil($expirySeconds / 60),
        );

        return wp_mail($email, $subject, $message, $headers);
    }
}
