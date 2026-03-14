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
        $subject = sprintf('[%s] Your verification code', $siteName);
        $message = sprintf(
            '<p>Your verification code is:</p>'
            . '<p style="font-size:24px;font-weight:bold;letter-spacing:4px;">%s</p>'
            . '<p>This code expires in %d minutes.</p>'
            . '<p>If you did not request this, please ignore this email.</p>',
            esc_html($otp),
            (int) ceil($expirySeconds / 60),
        );

        return wp_mail($email, $subject, $message, $headers);
    }
}
