<?php

namespace WP_SMS\Services\OTP\Delivery\PhoneNumber;

use WP_SMS\Components\Sms;
use WP_SMS\Services\OTP\Contracts\Interfaces\DeliveryChannelInterface;
use WP_SMS\Services\OTP\Delivery\PhoneNumber\Templating\TemplateRenderer;

/**
 * Class SmsChannel
 *
 * Delivery channel for sending OTP, magic links, and password reset messages over SMS.
 *
 * ## Usage
 *
 * $channel = new \WP_SMS\Services\OTP\Delivery\PhoneNumber\SmsChannel();
 *
 * // Send OTP code
 * $channel->send('+1234567890', '', [
 *     'template'           => SmsTemplate::TYPE_OTP_CODE,
 *     'otp_code'           => 123456,
 *     'expires_in_minutes' => '2',
 *     'user_display_name'  => 'John Doe',
 * ]);
 *
 * // Send magic login link
 * $channel->send('+1234567890', '', [
 *     'template'           => SmsTemplate::TYPE_MAGIC_LINK,
 *     'magic_link'         => 'https://example.com/magic-login?token=xyz',
 *     'expires_in_minutes' => '15',
 *     'user_display_name'  => 'John Doe',
 * ]);
 *
 * // Send combined message
 * $channel->send('+1234567890', '', [
 *     'template'           => SmsTemplate::TYPE_COMBINED_REGISTER,
 *     'otp_code'           => 123456,
 *     'magic_link'         => 'https://example.com/magic-login?token=xyz',
 *     'expires_in_minutes' => '15',
 *     'user_display_name'  => 'John Doe',
 * ]);
 *
 */
class SmsChannel implements DeliveryChannelInterface
{
    /**
     * @return string
     */
    public function getKey(): string
    {
        return 'sms';
    }

    /**
     * @param string $to
     * @param string $message
     * @param array $context
     * @return bool
     */
    public function send(string $to, string $message, array $context = []): bool
    {
        $templateId = $context['template'] ?? null;

        if ($templateId) {
            $renderer = new TemplateRenderer();
            $rendered = $renderer->render($templateId, $this->augmentContext($context));

            $message = $rendered['body'] ?? $message;
        } else {
            $message = $message ?: __('Your login code', 'wp-sms');
        }

        do_action('wpsms_sms_before_send', $to, $message, $context);

        //TODO: Implement actual SMS sending
        // $result = Sms::send([
        //     'to'  => $to,
        //     'msg' => $message,
        // ]);
        
        // For now, just log the message
        error_log("SMS sent to {$to}: {$message}");
        
        // Simulate successful sending
        $success = true;

        if ($success) {
            do_action('wpsms_log_event', 'sms_delivery_success', [
                'to'       => $to,
                'template' => $templateId ?: 'raw',
                'meta'     => ['message_length' => strlen($message)],
            ]);
            return true;
        }

        do_action('wpsms_log_event', 'sms_delivery_error', [
            'to'       => $to,
            'template' => $templateId ?: 'raw',
            'error'    => 'SMS sending failed',
        ]);

        return false;
    }

    /**
     * @param array $context
     * @return array
     */
    private function augmentContext(array $context): array
    {
        $context += [
            'site_name'         => wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
            'user_display_name' => $context['user_display_name'] ?? __('User', 'wp-sms'),
        ];
        return $context;
    }
}
