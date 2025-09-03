<?php

namespace WP_SMS\Services\OTP\Delivery\Email;

use WP_SMS\Services\Email\EmailService;
use WP_SMS\Services\OTP\Contracts\Interfaces\DeliveryChannelInterface;
use WP_SMS\Services\OTP\Delivery\Email\Templating\TemplateRenderer;

/**
 * Class EmailChannel
 *
 * Delivery channel for sending OTP, magic links, and password reset messages over email.
 *
 * ## Usage
 *
 * $channel = new \WP_SMS\Services\OTP\Delivery\Email\EmailChannel();
 *
 * // Send OTP code
 * $channel->send('user@example.com', '', [
 *     'template'           => EmailTemplate::TYPE_OTP_CODE,
 *     'otp_code'           => 123456,
 *     'expires_in_minutes' => '2',
 *     'user_display_name'  => 'John Doe',
 * ]);
 *
 * // Send magic login link
 * $channel->send('user@example.com', '', [
 *     'template'           => EmailTemplate::TYPE_MAGIC_LINK,
 *     'magic_link'         => 'https://example.com/magic-login?token=xyz',
 *     'expires_in_minutes' => '15',
 *     'user_display_name'  => 'John Doe',
 * ]);
 *
 * // Send password reset link
 * $channel->send('user@example.com', '', [
 *     'template'           => EmailTemplate::TYPE_PASSWORD_RESET,
 *     'reset_link'         => 'https://example.com/reset-password?token=abc',
 *     'expires_in_minutes' => '30',
 *     'user_display_name'  => 'John Doe',
 * ]);
 *
 */
class EmailChannel implements DeliveryChannelInterface
{
    /**
     * @return string
     */
    public function getKey(): string
    {
        return 'email';
    }

    /**
     * @param string $to
     * @param string $message
     * @param array $context
     * @return bool
     */
    public function send(string $to, string $message, array $context = []): bool
    {
        $subject = $context['subject'] ?? null;

        $headers = $context['headers'] ?? [];
        if (empty($headers)) {
            $headers = [];
        } elseif (!is_array($headers)) {
            $headers = [(string)$headers];
        }

        $templateId = $context['template'] ?? null;

        if ($templateId) {
            $renderer = new TemplateRenderer();
            $rendered = $renderer->render($templateId, $this->augmentContext($context));

            $subject = $subject ?: ($rendered['subject'] ?? '');
            $message = $rendered['body'] ?? $message;

            if (!empty($rendered['is_html'])) {
                $this->ensureHeader($headers, 'Content-Type: text/html; charset=UTF-8');
            } else {
                $this->ensureHeader($headers, 'Content-Type: text/plain; charset=UTF-8');
            }
        } else {
            $subject = $subject ?: __('Your Login info', 'wp-sms');
            $this->ensureHeader($headers, 'Content-Type: text/plain; charset=UTF-8');
        }

        do_action('wpsms_email_before_send', $to, $subject, $message, $headers, $context);

        $result = EmailService::send([
            'to'      => $to,
            'subject' => (string)$subject,
            'body'    => (string)$message,
            'headers' => $headers,
        ]);

        if ($result->success) {
            do_action('wpsms_log_event', 'email_delivery_success', [
                'to'       => $to,
                'template' => $templateId ?: 'raw',
                'meta'     => ['duration_ms' => $result->meta['ms'] ?? null],
            ]);
            return true;
        }

        do_action('wpsms_log_event', 'email_delivery_error', [
            'to'       => $to,
            'template' => $templateId ?: 'raw',
            'error'    => $result->error ?: 'Unknown error',
            'meta'     => ['duration_ms' => $result->meta['ms'] ?? null],
        ]);

        return false;
    }

    /**
     * @param array $headers
     * @param string $header
     * @return void
     */
    private function ensureHeader(array &$headers, string $header): void
    {
        foreach ($headers as $h) {
            if (stripos($h, 'content-type:') === 0) {
                return;
            }
        }
        $headers[] = $header;
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
