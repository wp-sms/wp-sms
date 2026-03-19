<?php

namespace WSms\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WSms\Messaging\MessageDispatcher;
use WSms\Messaging\Message\Message;
use WSms\Mfa\Channels\TelegramChannel;
use WSms\Telegram\TelegramBotClient;

defined('ABSPATH') || exit;

class TelegramController extends Controller
{
    public function __construct(
        private TelegramChannel $telegramChannel,
        private MessageDispatcher $messageDispatcher,
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
        // Validate webhook secret (check both MFA auth settings and Apps integration config).
        $headerSecret = $request->get_header('X-Telegram-Bot-Api-Secret-Token');

        if (!is_string($headerSecret) || !$this->isValidWebhookSecret($headerSecret)) {
            return new WP_REST_Response(['ok' => false], 403);
        }

        $body = $request->get_json_params();
        $message = $body['message'] ?? null;

        // Handle /start TOKEN for deep link MFA enrollment (highest priority).
        if ($message && !empty($message['text'])) {
            $text = trim($message['text']);
            $chatId = (int) ($message['chat']['id'] ?? 0);
            $username = $message['from']['username'] ?? null;

            if (preg_match('/^\/start\s+([a-f0-9]{32})$/', $text, $matches)) {
                $token = $matches[1];
                $linked = $this->telegramChannel->completeLinking($token, $chatId, $username);

                if ($linked) {
                    $this->messageDispatcher->sendImmediate(
                        new Message('telegram', (string) $chatId, __('Your Telegram account has been linked for MFA verification.', 'wp-sms'))
                    );
                }

                return new WP_REST_Response(['ok' => true]);
            }
        }

        // Dispatch typed WordPress actions for all update types.
        $this->dispatchUpdate($body);

        return new WP_REST_Response(['ok' => true]);
    }

    private function isValidWebhookSecret(string $headerSecret): bool
    {
        $secrets = [];

        $authSettings = get_option('wsms_auth_settings', []);
        if (!empty($authSettings['telegram']['webhook_secret'])) {
            $secrets[] = $authSettings['telegram']['webhook_secret'];
        }

        $configs = get_option('wsms_integration_configs', []);
        if (!empty($configs['telegram']['credentials']['webhook_secret'])) {
            $secrets[] = $configs['telegram']['credentials']['webhook_secret'];
        }

        foreach ($secrets as $secret) {
            if (hash_equals($secret, $headerSecret)) {
                return true;
            }
        }

        return false;
    }

    private function dispatchUpdate(array $body): void
    {
        $typeMap = [
            'message'           => 'wsms_telegram_message',
            'edited_message'    => 'wsms_telegram_edited_message',
            'channel_post'      => 'wsms_telegram_channel_post',
            'callback_query'    => 'wsms_telegram_callback_query',
            'my_chat_member'    => 'wsms_telegram_chat_member',
            'chat_member'       => 'wsms_telegram_chat_member',
            'chat_join_request' => 'wsms_telegram_chat_join_request',
        ];

        foreach ($typeMap as $key => $action) {
            if (isset($body[$key])) {
                do_action($action, $body[$key]);
            }
        }

        do_action('wsms_telegram_update', $body);
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
                'message' => __('Bot token is required.', 'wp-sms'),
            ], 400);
        }

        $client = new TelegramBotClient($botToken);

        // Validate the token.
        $me = $client->getMe();

        if (!$me) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'invalid_bot_token',
                'message' => __('Could not validate the bot token. Please check and try again.', 'wp-sms'),
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
                'message' => __('Bot token is valid but webhook setup failed.', 'wp-sms'),
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
            'message'      => __('Telegram bot configured successfully.', 'wp-sms'),
            'bot_username' => $me['username'] ?? '',
        ]);
    }
}
