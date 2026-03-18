<?php

namespace WSms\Rest;

use WSms\Auth\RateLimiter;
use WSms\Campaign\CampaignRepository;
use WSms\Log\Contracts\MessageLoggerInterface;
use WSms\Messaging\Contracts\SupportsStatusCallback;
use WSms\Messaging\Gateway\GatewayRegistry;

defined('ABSPATH') || exit;

class GatewayCallbackController
{
    private const NAMESPACE = 'wsms/v1';
    private const RATE_LIMIT = 120;
    private const RATE_WINDOW = 60;

    public function __construct(
        private readonly GatewayRegistry $gatewayRegistry,
        private readonly MessageLoggerInterface $messageLogger,
        private readonly RateLimiter $rateLimiter,
        private readonly ?CampaignRepository $campaignRepository = null,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/callbacks/(?P<gateway_id>[a-z_]+)/status', [
            [
                'methods'             => ['POST', 'GET'],
                'callback'            => [$this, 'handleStatusCallback'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(\WP_REST_Request $request): bool
    {
        $gatewayId = $request->get_param('gateway_id');
        $gateway = $this->gatewayRegistry->get($gatewayId);

        if (!$gateway) {
            return false;
        }

        if (!$gateway instanceof SupportsStatusCallback) {
            return false;
        }

        return $gateway->validateStatusCallback($request);
    }

    public function handleStatusCallback(\WP_REST_Request $request): \WP_REST_Response
    {
        $gatewayId = $request->get_param('gateway_id');

        $rateCheck = $this->rateLimiter->check('gateway_callback_' . $gatewayId, self::RATE_LIMIT, self::RATE_WINDOW);
        if (!$rateCheck['allowed']) {
            return new \WP_REST_Response([
                'success'     => false,
                'error'       => 'rate_limited',
                'retry_after' => $rateCheck['retry_after'],
            ], 429);
        }

        $gateway = $this->gatewayRegistry->get($gatewayId);

        /** @var SupportsStatusCallback $gateway */
        $updates = $gateway->parseStatusCallback($request);

        foreach ($updates as $update) {
            $logEntry = $this->messageLogger->findByProviderId($gatewayId, $update->providerId);
            if ($logEntry === null) {
                continue;
            }

            $this->messageLogger->updateStatus($logEntry['id'], $update->status, $update->getDisplayError());

            // Increment campaign counters on delivery receipt
            if ($this->campaignRepository && ($logEntry['campaign_id'] ?? null)) {
                if ($update->status === 'delivered') {
                    $this->campaignRepository->incrementCounters($logEntry['campaign_id'], 'delivered_count');
                } elseif ($update->status === 'failed') {
                    $this->campaignRepository->incrementCounters($logEntry['campaign_id'], 'failed_count');
                }
            }
        }

        return new \WP_REST_Response(['success' => true]);
    }
}
