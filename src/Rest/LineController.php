<?php

namespace WSms\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WSms\Line\LineBotClient;
use WSms\Messaging\Gateway\Line\LineGateway;
use WSms\Messaging\MessageDispatcher;
use WSms\Messaging\Message\Message;
use WSms\Mfa\Channels\LineChannel;

defined('ABSPATH') || exit;

class LineController extends Controller
{
    private const HOOK_MAP = [
        'message'      => 'wsms_line_message',
        'follow'       => 'wsms_line_follow',
        'unfollow'     => 'wsms_line_unfollow',
        'postback'     => 'wsms_line_postback',
        'memberJoined' => 'wsms_line_member_joined',
        'memberLeft'   => 'wsms_line_member_left',
        'accountLink'  => 'wsms_line_account_link',
        'beacon'       => 'wsms_line_beacon',
    ];

    public function __construct(
        private LineBotClient $client,
        private MessageDispatcher $messageDispatcher,
        private ?LineChannel $lineChannel = null,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/line/webhook', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleWebhook'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Handle incoming Line webhook events.
     * Verifies HMAC-SHA256 signature on raw body, then routes events.
     */
    public function handleWebhook(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            // Verify signature on raw body BEFORE JSON parsing.
            $body = $request->get_body();
            $signature = $request->get_header('x-line-signature');
            $secret = LineGateway::resolveChannelSecret();

            if (empty($signature) || empty($secret) || !LineBotClient::verifySignature($body, $signature, $secret)) {
                return new WP_REST_Response(['error' => __('Invalid signature', 'wp-sms')], 403);
            }

            $data = json_decode($body, true);
            $events = $data['events'] ?? [];

            foreach ($events as $event) {
                // Deduplication via webhookEventId.
                $eventId = $event['webhookEventId'] ?? '';
                if (!empty($eventId) && $this->isDuplicate($eventId)) {
                    continue;
                }
                if (!empty($eventId)) {
                    $this->markProcessed($eventId);
                }

                $this->routeEvent($event);
            }

            return new WP_REST_Response(null, 200);
        });
    }

    private function routeEvent(array $event): void
    {
        $type = $event['type'] ?? '';
        $source = $event['source'] ?? [];

        // Deep link MFA enrollment: user sends "link_<token>" text.
        if ($type === 'message' && ($event['message']['type'] ?? '') === 'text') {
            $text = trim($event['message']['text'] ?? '');

            if (preg_match('/^link_([a-f0-9]{32})$/', $text, $matches) && $this->lineChannel) {
                $userId = $source['userId'] ?? '';
                $profile = $this->client->getProfile($userId);
                $linked = $this->lineChannel->completeLinking($matches[1], $userId, $profile['displayName'] ?? null);

                if ($linked) {
                    $this->messageDispatcher->sendImmediate(
                        new Message('line', $userId, __('Your LINE account has been linked for MFA verification.', 'wp-sms'))
                    );
                }

                return;
            }
        }

        // Contact management.
        if ($type === 'follow') {
            $this->handleFollow($source['userId'] ?? '');
        } elseif ($type === 'unfollow') {
            $this->handleUnfollow($source['userId'] ?? '');
        }

        $hook = self::HOOK_MAP[$type] ?? null;

        if ($hook) {
            do_action($hook, $event);
        }

        // Always fire generic hook.
        do_action('wsms_line_event', $event);
    }

    private function handleFollow(string $userId): void
    {
        if (empty($userId) || !has_action('wsms_line_contact_followed')) {
            return;
        }

        $profile = $this->client->getProfile($userId);

        do_action('wsms_line_contact_followed', $userId, $profile);
    }

    private function handleUnfollow(string $userId): void
    {
        if (empty($userId)) {
            return;
        }

        do_action('wsms_line_contact_unfollowed', $userId);
    }

    private function isDuplicate(string $eventId): bool
    {
        return get_transient('wsms_line_evt_' . $eventId) !== false;
    }

    private function markProcessed(string $eventId): void
    {
        set_transient('wsms_line_evt_' . $eventId, 1, 86400);
    }
}
