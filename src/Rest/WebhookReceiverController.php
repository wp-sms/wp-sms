<?php

namespace WSms\Rest;

use WSms\Auth\RateLimiter;
use WSms\Integration\Webhook\WebhookIntegration;

defined('ABSPATH') || exit;

class WebhookReceiverController extends Controller
{
    private const RATE_LIMIT = 60;
    private const RATE_WINDOW = 60;

    public function __construct(
        private readonly RateLimiter $rateLimiter,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/webhook/(?P<id>[A-Za-z0-9_-]+)', [
            [
                'methods'             => ['POST', 'PUT'],
                'callback'            => [$this, 'receive'],
                'permission_callback' => [$this, 'verifyWebhookSignature'],
            ],
        ]);
    }

    public function verifyWebhookSignature(\WP_REST_Request $request): bool
    {
        $webhookId = $request->get_param('id');
        $configs = get_option(WebhookIntegration::SECRETS_OPTION, []);
        $secret = $configs[$webhookId]['secret'] ?? '';

        if (empty($secret)) {
            return false;
        }

        $signature = $request->get_header('x-webhook-signature');
        if (empty($signature)) {
            return false;
        }

        $body = $request->get_body();
        $expected = hash_hmac('sha256', $body, $secret);

        return hash_equals($expected, $signature);
    }

    public function receive(\WP_REST_Request $request): \WP_REST_Response
    {
        $webhookId = $request->get_param('id');
        $rateCheck = $this->rateLimiter->check('webhook_receive_' . $webhookId, self::RATE_LIMIT, self::RATE_WINDOW);

        if (!$rateCheck['allowed']) {
            return new \WP_REST_Response([
                'success'     => false,
                'error'       => 'rate_limited',
                'message'     => __('Too many requests. Please try again later.', 'wp-sms'),
                'retry_after' => $rateCheck['retry_after'],
            ], 429);
        }

        do_action('wsms_webhook_received', [
            'webhook_id' => $webhookId,
            'body'       => $request->get_json_params() ?: $request->get_params(),
            'headers'    => $request->get_headers(),
            'method'     => $request->get_method(),
        ]);

        return new \WP_REST_Response(['success' => true]);
    }
}
