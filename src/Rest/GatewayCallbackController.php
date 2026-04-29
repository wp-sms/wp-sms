<?php

namespace WSms\Rest;

use WSms\Auth\RateLimiter;
use WSms\Campaign\CampaignRepository;
use WSms\Contact\StatusPropagator;
use WSms\Log\Contracts\MessageLoggerInterface;
use WSms\Messaging\Contracts\SupportsCustomCallbackResponse;
use WSms\Messaging\Contracts\SupportsInboundMessage;
use WSms\Messaging\Contracts\SupportsStatusCallback;
use WSms\Messaging\Gateway\GatewayRegistry;
use WSms\Messaging\Inbound\OptOutManager;

defined('ABSPATH') || exit;

class GatewayCallbackController extends Controller
{
    private const RATE_LIMIT = 120;
    private const RATE_WINDOW = 60;

    public function __construct(
        private readonly GatewayRegistry $gatewayRegistry,
        private readonly MessageLoggerInterface $messageLogger,
        private readonly RateLimiter $rateLimiter,
        private readonly ?CampaignRepository $campaignRepository = null,
        private readonly ?OptOutManager $optOutManager = null,
        private readonly ?StatusPropagator $statusPropagator = null,
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

        register_rest_route(self::NAMESPACE, '/callbacks/(?P<gateway_id>[a-z_]+)/inbound', [
            [
                'methods'             => ['POST', 'GET'],
                'callback'            => [$this, 'handleInboundCallback'],
                'permission_callback' => [$this, 'checkInboundPermission'],
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
        return $this->handle(function () use ($request) {
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

                // Propagate permanent failures, complaints, and unsubscribes to contact status
                if ($this->statusPropagator !== null && ($update->permanent || $update->complaint || $update->unsubscribe)) {
                    $this->statusPropagator->propagate($update, $logEntry['recipient'], $logEntry['channel']);
                }
            }

            $this->scheduleCustomCallbackBody($gateway, 'status', $request);

            return $this->ok();
        });
    }

    public function checkInboundPermission(\WP_REST_Request $request): bool
    {
        $gatewayId = $request->get_param('gateway_id');
        $gateway = $this->gatewayRegistry->get($gatewayId);

        if (!$gateway) {
            return false;
        }

        if (!$gateway instanceof SupportsInboundMessage) {
            return false;
        }

        return $gateway->validateInboundCallback($request);
    }

    public function handleInboundCallback(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $gatewayId = $request->get_param('gateway_id');

            $rateCheck = $this->rateLimiter->check('gateway_inbound_' . $gatewayId, self::RATE_LIMIT, self::RATE_WINDOW);
            if (!$rateCheck['allowed']) {
                return new \WP_REST_Response([
                    'success'     => false,
                    'error'       => 'rate_limited',
                    'retry_after' => $rateCheck['retry_after'],
                ], 429);
            }

            if ($this->optOutManager === null) {
                return new \WP_REST_Response(['success' => false, 'error' => 'not_configured'], 500);
            }

            $gateway = $this->gatewayRegistry->get($gatewayId);

            /** @var SupportsInboundMessage $gateway */
            $messages = $gateway->parseInboundCallback($request);

            foreach ($messages as $message) {
                $this->optOutManager->processInboundMessage($message, $gatewayId);
            }

            $this->scheduleCustomCallbackBody($gateway, 'inbound', $request);

            return $this->ok();
        });
    }

    /**
     * Some providers (e.g. SMSAPI) require the webhook receiver to reply with a
     * specific plain-text body — not WSMS's default JSON envelope. When the
     * gateway opts in via SupportsCustomCallbackResponse, hook the WP REST
     * server's pre-serve filter to emit that body and short-circuit the JSON
     * encoding for this single response.
     */
    private function scheduleCustomCallbackBody(mixed $gateway, string $type, \WP_REST_Request $request): void
    {
        if (!$gateway instanceof SupportsCustomCallbackResponse) {
            return;
        }
        $body = $gateway->getCallbackResponseBody($type, $request);
        if ($body === null) {
            return;
        }

        add_filter('rest_pre_serve_request', static function ($served) use ($body) {
            if ($served) {
                return $served;
            }
            if (!headers_sent()) {
                @header('Content-Type: text/plain; charset=utf-8');
            }
            echo $body;
            return true;
        }, 10, 1);
    }
}
