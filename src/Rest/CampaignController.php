<?php

namespace WSms\Rest;

use WSms\Campaign\AudienceResolver;
use WSms\Campaign\Campaign;
use WSms\Campaign\CampaignDispatcher;
use WSms\Campaign\CampaignRepository;
use WSms\Exception\ConflictException;
use WSms\Exception\NotFoundException;
use WSms\Exception\PersistenceException;
use WSms\Exception\ValidationException;
use WSms\Log\Contracts\MessageLoggerInterface;
use WSms\Messaging\Gateway\GatewayRegistry;
use WSms\Messaging\MessageDispatcher;
use WSms\Messaging\Message\Message;

defined('ABSPATH') || exit;

class CampaignController extends Controller
{
    public function __construct(
        private readonly CampaignRepository $campaignRepository,
        private readonly CampaignDispatcher $campaignDispatcher,
        private readonly AudienceResolver $audienceResolver,
        private readonly MessageLoggerInterface $messageLogger,
        private readonly MessageDispatcher $messageDispatcher,
        private readonly GatewayRegistry $gatewayRegistry,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/campaigns', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'index'],
                'permission_callback' => $this->canViewSection('campaigns'),
                'args'                => [
                    'status'   => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'channel'  => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'per_page' => ['type' => 'integer', 'default' => 50],
                    'page'     => ['type' => 'integer', 'default' => 1],
                ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'store'],
                'permission_callback' => $this->canManageSection('campaigns'),
                'args'                => [
                    'name'        => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'channel'     => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'body'        => ['type' => 'string'],
                    'gateway_id'  => ['type' => ['string', 'null'], 'sanitize_callback' => 'sanitize_text_field'],
                    'subject'     => ['type' => ['string', 'null'], 'sanitize_callback' => 'sanitize_text_field'],
                    'audience'    => ['type' => 'object'],
                    'compliance'  => ['type' => ['object', 'null']],
                    'timezone'    => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'recurrence'  => ['type' => ['object', 'null']],
                    'quiet_hours' => ['type' => ['object', 'null']],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/campaigns/media', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'uploadMedia'],
                'permission_callback' => $this->canManageSection('campaigns'),
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/campaigns/audience-count', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'audienceCount'],
                'permission_callback' => $this->canManageSection('campaigns'),
                'args'                => [
                    'audience' => ['required' => true, 'type' => 'object'],
                    'channel'  => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/campaigns/(?P<id>[A-Za-z0-9]+)', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'show'],
                'permission_callback' => $this->canViewSection('campaigns'),
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'update'],
                'permission_callback' => $this->canManageSection('campaigns'),
                'args'                => [
                    'name'        => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'channel'     => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'body'        => ['type' => 'string'],
                    'gateway_id'  => ['type' => ['string', 'null'], 'sanitize_callback' => 'sanitize_text_field'],
                    'subject'     => ['type' => ['string', 'null'], 'sanitize_callback' => 'sanitize_text_field'],
                    'audience'    => ['type' => 'object'],
                    'compliance'  => ['type' => ['object', 'null']],
                    'timezone'    => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'recurrence'  => ['type' => ['object', 'null']],
                    'quiet_hours' => ['type' => ['object', 'null']],
                ],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [$this, 'destroy'],
                'permission_callback' => $this->canManageSection('campaigns'),
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/campaigns/(?P<id>[A-Za-z0-9]+)/send', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'send'],
                'permission_callback' => $this->canManageSection('campaigns'),
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/campaigns/(?P<id>[A-Za-z0-9]+)/schedule', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'schedule'],
                'permission_callback' => $this->canManageSection('campaigns'),
                'args'                => [
                    'send_at'  => ['required' => true, 'type' => 'string'],
                    'timezone' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/campaigns/(?P<id>[A-Za-z0-9]+)/cancel', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'cancel'],
                'permission_callback' => $this->canManageSection('campaigns'),
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/campaigns/(?P<id>[A-Za-z0-9]+)/pause', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'pause'],
                'permission_callback' => $this->canManageSection('campaigns'),
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/campaigns/(?P<id>[A-Za-z0-9]+)/resume', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'resume'],
                'permission_callback' => $this->canManageSection('campaigns'),
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/campaigns/(?P<id>[A-Za-z0-9]+)/duplicate', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'duplicate'],
                'permission_callback' => $this->canManageSection('campaigns'),
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/campaigns/(?P<id>[A-Za-z0-9]+)/stats', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'stats'],
                'permission_callback' => $this->canViewSection('campaigns'),
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/campaigns/(?P<id>[A-Za-z0-9]+)/test', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'test'],
                'permission_callback' => $this->canManageSection('campaigns'),
                'args'                => [
                    'recipient' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/campaigns/(?P<id>[A-Za-z0-9]+)/recipients', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'recipients'],
                'permission_callback' => $this->canViewSection('campaigns'),
                'args'                => [
                    'per_page'  => ['type' => 'integer', 'default' => 50],
                    'page'      => ['type' => 'integer', 'default' => 1],
                    'status'    => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'recipient' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                ],
            ],
        ]);
    }

    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $filters = [];
            if ($request->get_param('status')) {
                $filters['status'] = $request->get_param('status');
            }
            if ($request->get_param('channel')) {
                $filters['channel'] = $request->get_param('channel');
            }

            $perPage = (int) $request->get_param('per_page');
            $page = max(1, (int) $request->get_param('page'));
            $offset = ($page - 1) * $perPage;

            $campaigns = $this->campaignRepository->findAll($filters, $perPage, $offset);
            $total = $this->campaignRepository->count($filters);

            return $this->paginated(
                array_map(fn(Campaign $c) => $c->toArray(), $campaigns),
                $total,
            );
        });
    }

    public function store(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $params = $request->get_params();

            $campaign = new Campaign(
                id: '',
                name: $params['name'],
                channel: $params['channel'],
                gatewayId: $params['gateway_id'] ?? null,
                status: 'draft',
                body: $params['body'] ?? '',
                audience: $params['audience'] ?? ['sources' => []],
                subject: $params['subject'] ?? null,
                compliance: $params['compliance'] ?? null,
                timezone: $params['timezone'] ?? 'UTC',
                recurrence: $params['recurrence'] ?? null,
                quietHours: $params['quiet_hours'] ?? null,
                createdBy: get_current_user_id(),
            );

            $id = $this->campaignRepository->save($campaign);
            $saved = $this->campaignRepository->find($id);

            if (!$saved) {
                throw new PersistenceException(__('Failed to save campaign', 'wp-sms'));
            }

            return $this->created($saved->toArray());
        });
    }

    public function show(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $id = $request->get_param('id');
            $campaign = $this->campaignRepository->find($id);

            if (!$campaign) {
                throw NotFoundException::entity('Campaign', $id);
            }

            return $this->ok($campaign->toArray());
        });
    }

    public function update(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $id = $request->get_param('id');
            $existing = $this->campaignRepository->find($id);

            if (!$existing) {
                throw NotFoundException::entity('Campaign', $id);
            }

            if ($existing->getStatus() === 'sending') {
                throw new ConflictException(__('Cannot edit a campaign that is currently sending', 'wp-sms'));
            }

            $params = $request->get_params();

            $campaign = new Campaign(
                id: $id,
                name: $params['name'] ?? $existing->getName(),
                channel: $params['channel'] ?? $existing->getChannel(),
                gatewayId: array_key_exists('gateway_id', $params) ? $params['gateway_id'] : $existing->getGatewayId(),
                status: $existing->getStatus(),
                body: $params['body'] ?? $existing->getBody(),
                audience: $params['audience'] ?? $existing->getAudience(),
                subject: array_key_exists('subject', $params) ? $params['subject'] : $existing->getSubject(),
                compliance: array_key_exists('compliance', $params) ? $params['compliance'] : $existing->getCompliance(),
                sendAt: $existing->getSendAt(),
                timezone: $params['timezone'] ?? $existing->getTimezone(),
                recurrence: array_key_exists('recurrence', $params) ? $params['recurrence'] : $existing->getRecurrence(),
                quietHours: array_key_exists('quiet_hours', $params) ? $params['quiet_hours'] : $existing->getQuietHours(),
                parentId: $existing->getParentId(),
                totalRecipients: $existing->getTotalRecipients(),
                sentCount: $existing->getSentCount(),
                deliveredCount: $existing->getDeliveredCount(),
                failedCount: $existing->getFailedCount(),
                skippedCount: $existing->getSkippedCount(),
                totalCost: $existing->getTotalCost(),
                lastProcessedId: $existing->getLastProcessedId(),
                createdBy: $existing->getCreatedBy(),
                startedAt: $existing->getStartedAt(),
                completedAt: $existing->getCompletedAt(),
                cancelledAt: $existing->getCancelledAt(),
            );

            $this->campaignRepository->save($campaign);

            return $this->ok($campaign->toArray());
        });
    }

    public function destroy(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $id = $request->get_param('id');
            $campaign = $this->campaignRepository->find($id);

            if (!$campaign) {
                throw NotFoundException::entity('Campaign', $id);
            }

            if ($campaign->getStatus() === 'sending') {
                throw new ConflictException(__('Cannot delete a campaign that is currently sending', 'wp-sms'));
            }

            $this->campaignRepository->delete($id);

            return $this->ok();
        });
    }

    public function send(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $id = $request->get_param('id');
            $campaign = $this->campaignRepository->find($id);

            if (!$campaign) {
                throw NotFoundException::entity('Campaign', $id);
            }

            $dispatched = $this->campaignDispatcher->dispatch($id);

            if (!$dispatched) {
                throw ValidationException::field('campaign', __('Failed to dispatch campaign', 'wp-sms'));
            }

            $updated = $this->campaignRepository->find($id);

            return $this->ok($updated->toArray());
        });
    }

    public function schedule(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $id = $request->get_param('id');
            $campaign = $this->campaignRepository->find($id);

            if (!$campaign) {
                throw NotFoundException::entity('Campaign', $id);
            }

            $sendAtStr = $request->get_param('send_at');
            $timezone = $request->get_param('timezone') ?? $campaign->getTimezone();

            // Convert to UTC for storage
            $tz = new \DateTimeZone($timezone);
            $sendAt = new \DateTimeImmutable($sendAtStr, $tz);
            $sendAtUtc = $sendAt->setTimezone(new \DateTimeZone('UTC'));

            $scheduled = $this->campaignDispatcher->schedule($id, $sendAtUtc);

            if (!$scheduled) {
                throw ValidationException::field('campaign', __('Failed to schedule campaign. Campaign must be in draft status.', 'wp-sms'));
            }

            // Also save the timezone
            $this->campaignRepository->updateStatus($id, 'scheduled', [
                'timezone' => $timezone,
            ]);

            $updated = $this->campaignRepository->find($id);

            return $this->ok($updated->toArray());
        });
    }

    public function cancel(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $id = $request->get_param('id');
            $cancelled = $this->campaignDispatcher->cancel($id);

            if (!$cancelled) {
                throw ValidationException::field('campaign', __('Failed to cancel campaign', 'wp-sms'));
            }

            $updated = $this->campaignRepository->find($id);

            return $this->ok($updated->toArray());
        });
    }

    public function pause(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $id = $request->get_param('id');
            $paused = $this->campaignDispatcher->pause($id);

            if (!$paused) {
                throw ValidationException::field('campaign', __('Failed to pause campaign. Campaign must be sending.', 'wp-sms'));
            }

            $updated = $this->campaignRepository->find($id);

            return $this->ok($updated->toArray());
        });
    }

    public function resume(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $id = $request->get_param('id');
            $resumed = $this->campaignDispatcher->resume($id);

            if (!$resumed) {
                throw ValidationException::field('campaign', __('Failed to resume campaign. Campaign must be paused.', 'wp-sms'));
            }

            $updated = $this->campaignRepository->find($id);

            return $this->ok($updated->toArray());
        });
    }

    public function duplicate(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $id = $request->get_param('id');
            $existing = $this->campaignRepository->find($id);

            if (!$existing) {
                throw NotFoundException::entity('Campaign', $id);
            }

            $clone = new Campaign(
                id: '',
                name: sprintf(__('Copy of %s', 'wp-sms'), $existing->getName()),
                channel: $existing->getChannel(),
                gatewayId: $existing->getGatewayId(),
                status: 'draft',
                body: $existing->getBody(),
                audience: $existing->getAudience(),
                subject: $existing->getSubject(),
                compliance: $existing->getCompliance(),
                timezone: $existing->getTimezone(),
                quietHours: $existing->getQuietHours(),
                createdBy: get_current_user_id(),
            );

            $cloneId = $this->campaignRepository->save($clone);
            $saved = $this->campaignRepository->find($cloneId);

            return $this->created($saved->toArray());
        });
    }

    public function stats(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $id = $request->get_param('id');
            $campaign = $this->campaignRepository->find($id);

            if (!$campaign) {
                throw NotFoundException::entity('Campaign', $id);
            }

            $stats = $this->messageLogger->getCampaignStats($id);

            $supportsDelivery = false;
            $gatewayId = $campaign->getGatewayId();
            if ($gatewayId) {
                $gateway = $this->gatewayRegistry->get($gatewayId);
                if ($gateway) {
                    $supportsDelivery = $gateway->getFeatures()['delivery_receipt'] ?? false;
                }
            }

            return $this->ok([
                'total_recipients'          => $campaign->getTotalRecipients(),
                'skipped_count'             => $campaign->getSkippedCount(),
                'supports_delivery_receipt' => $supportsDelivery,
                ...$stats,
            ]);
        });
    }

    public function test(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $id = $request->get_param('id');
            $campaign = $this->campaignRepository->find($id);

            if (!$campaign) {
                throw NotFoundException::entity('Campaign', $id);
            }

            $recipient = $request->get_param('recipient');
            $body = $campaign->getBody();

            // Append opt-out text for test
            $compliance = $campaign->getCompliance() ?? [];
            if (!empty($compliance['append_opt_out']) && !empty($compliance['opt_out_text'])) {
                $body .= "\n\n" . $compliance['opt_out_text'];
            }

            $message = new Message(
                channel: $campaign->getChannel(),
                recipient: $recipient,
                body: $body,
                meta: ['subject' => $campaign->getSubject()],
            );

            $result = $this->messageDispatcher->sendImmediate($message, $campaign->getGatewayId());

            return new \WP_REST_Response([
                'success' => $result->success,
                'data'    => [
                    'status' => $result->success ? $result->status : 'failed',
                    'error'  => $result->error,
                ],
            ], $result->success ? 200 : 400);
        });
    }

    public function uploadMedia(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $files = $request->get_file_params();

            if (empty($files['file'])) {
                throw ValidationException::field('file', __('No file uploaded', 'wp-sms'));
            }

            $file = $files['file'];

            require_once ABSPATH . 'wp-admin/includes/file.php';

            // wp_handle_upload performs server-side MIME validation via the mimes override
            $overrides = [
                'test_form' => false,
                'mimes'     => [
                    'jpg|jpeg' => 'image/jpeg',
                    'png'      => 'image/png',
                    'gif'      => 'image/gif',
                    'webp'     => 'image/webp',
                ],
            ];

            $uploaded = wp_handle_upload($file, $overrides);

            if (isset($uploaded['error'])) {
                throw ValidationException::field('file', $uploaded['error']);
            }

            return new \WP_REST_Response([
                'success' => true,
                'url'     => $uploaded['url'],
            ]);
        });
    }

    public function audienceCount(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $audience = $request->get_param('audience');
            $channel = $request->get_param('channel');

            $count = $this->audienceResolver->count($audience, $channel);

            return $this->ok(['count' => $count]);
        });
    }

    public function recipients(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $id = $request->get_param('id');
            $campaign = $this->campaignRepository->find($id);

            if (!$campaign) {
                throw NotFoundException::entity('Campaign', $id);
            }

            $perPage = (int) $request->get_param('per_page');
            $page = max(1, (int) $request->get_param('page'));
            $offset = ($page - 1) * $perPage;

            $filters = ['campaign_id' => $id];
            if ($request->get_param('status')) {
                $filters['status'] = $request->get_param('status');
            }
            if ($request->get_param('recipient')) {
                $filters['recipient'] = $request->get_param('recipient');
            }

            $logs = $this->messageLogger->findAll($filters, $perPage, $offset);
            $total = $this->messageLogger->count($filters);

            return $this->paginated($logs, $total);
        });
    }
}
