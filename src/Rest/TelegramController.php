<?php

namespace WSms\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WSms\Mfa\Channels\TelegramChannel;
use WSms\Telegram\TelegramBotClient;

defined('ABSPATH') || exit;

class TelegramController
{
    private const NAMESPACE = 'wsms/v1';

    public function __construct(
        private TelegramChannel $telegramChannel,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/telegram/webhook', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleWebhook'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/telegram/setup', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleSetup'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);
    }

    /**
     * Handle incoming Telegram webhook updates.
     * Validates the secret token header and processes /start commands for enrollment.
     */
    public function handleWebhook(WP_REST_Request $request): WP_REST_Response
    {
        $settings = get_option('wsms_auth_settings', []);
        $expectedSecret = $settings['telegram']['webhook_secret'] ?? '';

        // Validate webhook secret.
        $headerSecret = $request->get_header('X-Telegram-Bot-Api-Secret-Token');

        if (empty($expectedSecret) || $headerSecret !== $expectedSecret) {
            return new WP_REST_Response(['ok' => false], 403);
        }

        $body = $request->get_json_params();
        $message = $body['message'] ?? null;

        if (!$message || empty($message['text'])) {
            return new WP_REST_Response(['ok' => true]);
        }

        $text = trim($message['text']);
        $chatId = (int) ($message['chat']['id'] ?? 0);
        $username = $message['from']['username'] ?? null;

        // Handle /start TOKEN for deep link enrollment.
        if (preg_match('/^\/start\s+([a-f0-9]{32})$/', $text, $matches)) {
            $token = $matches[1];
            $linked = $this->telegramChannel->completeLinking($token, $chatId, $username);

            if ($linked) {
                $botToken = $settings['telegram']['bot_token'] ?? '';

                if ($botToken) {
                    $client = new TelegramBotClient($botToken);
                    $client->sendMessage($chatId, 'Your Telegram account has been linked for MFA verification.');
                }
            }
        }

        return new WP_REST_Response(['ok' => true]);
    }

    /**
     * Validate bot token and set up webhook.
     */
    public function handleSetup(WP_REST_Request $request): WP_REST_Response
    {
        $botToken = $request->get_param('bot_token');

        if (empty($botToken)) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'missing_bot_token',
                'message' => 'Bot token is required.',
            ], 400);
        }

        $client = new TelegramBotClient($botToken);

        // Validate the token.
        $me = $client->getMe();

        if (!$me) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'invalid_bot_token',
                'message' => 'Could not validate the bot token. Please check and try again.',
            ], 400);
        }

        // Generate webhook secret.
        $webhookSecret = bin2hex(random_bytes(32));
        $webhookUrl = rest_url(self::NAMESPACE . '/telegram/webhook');

        $webhookSet = $client->setWebhook($webhookUrl, $webhookSecret);

        if (!$webhookSet) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'webhook_setup_failed',
                'message' => 'Bot token is valid but webhook setup failed.',
            ], 500);
        }

        // Save settings.
        $settings = get_option('wsms_auth_settings', []);
        $settings['telegram'] = array_merge($settings['telegram'] ?? [], [
            'bot_token'      => $botToken,
            'bot_username'   => $me['username'] ?? '',
            'webhook_secret' => $webhookSecret,
        ]);
        update_option('wsms_auth_settings', $settings);

        return new WP_REST_Response([
            'success'      => true,
            'message'      => 'Telegram bot configured successfully.',
            'bot_username' => $me['username'] ?? '',
        ]);
    }
}
