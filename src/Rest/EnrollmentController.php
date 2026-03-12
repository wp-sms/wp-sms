<?php

namespace WSms\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WSms\Auth\PolicyEngine;
use WSms\Enums\ChannelStatus;
use WSms\Mfa\Channels\PhoneChannel;
use WSms\Mfa\Channels\BackupCodesChannel;
use WSms\Mfa\MfaManager;

defined('ABSPATH') || exit;

class EnrollmentController
{
    private const NAMESPACE = 'wsms/v1';

    public function __construct(
        private MfaManager $mfaManager,
        private PolicyEngine $policy,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/auth/methods', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handleListMethods'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/auth/factors', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handleListFactors'],
            'permission_callback' => [$this, 'checkAuthenticated'],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/mfa/enroll', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleEnroll'],
            'permission_callback' => [$this, 'checkAuthenticated'],
            'args'                => [
                'channel_id' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'data'       => ['required' => false, 'type' => 'object', 'default' => []],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/mfa/enroll/verify', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleEnrollVerify'],
            'permission_callback' => [$this, 'checkAuthenticated'],
            'args'                => [
                'channel_id' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'code'       => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/mfa/unenroll', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'handleUnenroll'],
            'permission_callback' => [$this, 'checkAuthenticated'],
            'args'                => [
                'channel_id' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/mfa/backup-codes/regenerate', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleRegenerateBackupCodes'],
            'permission_callback' => [$this, 'checkAuthenticated'],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/me', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handleMe'],
            'permission_callback' => [$this, 'checkAuthenticated'],
        ]);
    }

    public function checkAuthenticated(WP_REST_Request $request): bool
    {
        return is_user_logged_in();
    }

    public function handleListMethods(WP_REST_Request $request): WP_REST_Response
    {
        $channels = $this->mfaManager->getAvailableChannels();
        $methods = [];

        foreach ($channels as $channel) {
            $methods[] = [
                'id'                   => $channel->getId(),
                'name'                 => $channel->getName(),
                'supports_primary'     => $channel->supportsPrimaryAuth(),
                'supports_mfa'         => $channel->supportsMfa(),
            ];
        }

        return new WP_REST_Response(['methods' => $methods]);
    }

    public function handleListFactors(WP_REST_Request $request): WP_REST_Response
    {
        $userId = get_current_user_id();
        $factors = $this->mfaManager->getUserFactors($userId);
        $enrolled = [];

        foreach ($factors as $factor) {
            if ($factor->status !== ChannelStatus::Active) {
                continue;
            }

            $channel = $this->mfaManager->getChannel($factor->channelId);
            $enrolled[] = [
                'channel_id' => $factor->channelId,
                'name'       => $channel ? $channel->getName() : $factor->channelId,
                'info'       => $channel ? $channel->getEnrollmentInfo($userId) : [],
            ];
        }

        return new WP_REST_Response(['factors' => $enrolled]);
    }

    public function handleEnroll(WP_REST_Request $request): WP_REST_Response
    {
        $userId = get_current_user_id();
        $channelId = $request->get_param('channel_id');
        $data = $request->get_param('data') ?? [];

        $channel = $this->mfaManager->getChannel($channelId);

        if (!$channel) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'invalid_channel',
                'message' => 'Unknown channel.',
            ], 400);
        }

        $result = $channel->enroll($userId, $data);

        if ($result->success) {
            // Auto-enroll backup codes on first MFA factor.
            if ($channelId !== 'backup_codes') {
                $backupChannel = $this->mfaManager->getChannel('backup_codes');

                if ($backupChannel && !$backupChannel->isEnrolled($userId)) {
                    $backupResult = $backupChannel->enroll($userId, []);

                    if ($backupResult->success) {
                        $result = new \WSms\Mfa\ValueObjects\EnrollmentResult(
                            $result->success,
                            $result->message,
                            array_merge($result->data, ['backup_codes' => $backupResult->data['codes'] ?? []]),
                        );
                    }
                }
            }

            update_user_meta($userId, 'wsms_mfa_enabled', '1');
        }

        return new WP_REST_Response([
            'success' => $result->success,
            'message' => $result->message,
            'data'    => $result->data,
        ], $result->success ? 200 : 400);
    }

    public function handleEnrollVerify(WP_REST_Request $request): WP_REST_Response
    {
        $userId = get_current_user_id();
        $channelId = $request->get_param('channel_id');
        $code = $request->get_param('code');

        $channel = $this->mfaManager->getChannel($channelId);

        if (!$channel) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'invalid_channel',
                'message' => 'Unknown channel.',
            ], 400);
        }

        if ($channel instanceof PhoneChannel) {
            $result = $channel->confirmEnrollment($userId, $code);

            if ($result->success) {
                update_user_meta($userId, 'wsms_mfa_enabled', '1');
            }

            return new WP_REST_Response([
                'success' => $result->success,
                'message' => $result->message,
            ], $result->success ? 200 : 400);
        }

        return new WP_REST_Response([
            'success' => false,
            'error'   => 'not_applicable',
            'message' => 'This channel does not require enrollment verification.',
        ], 400);
    }

    public function handleUnenroll(WP_REST_Request $request): WP_REST_Response
    {
        $userId = get_current_user_id();
        $channelId = $request->get_param('channel_id');

        $channel = $this->mfaManager->getChannel($channelId);

        if (!$channel) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'invalid_channel',
                'message' => 'Unknown channel.',
            ], 400);
        }

        $result = $channel->unenroll($userId);

        if (!$this->mfaManager->hasActiveFactors($userId)) {
            update_user_meta($userId, 'wsms_mfa_enabled', '0');
        }

        return new WP_REST_Response([
            'success' => $result,
            'message' => $result ? $channel->getName() . ' has been disabled.' : 'Failed to unenroll.',
        ], $result ? 200 : 400);
    }

    public function handleRegenerateBackupCodes(WP_REST_Request $request): WP_REST_Response
    {
        $userId = get_current_user_id();
        $channel = $this->mfaManager->getChannel('backup_codes');

        if (!$channel || !($channel instanceof BackupCodesChannel)) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'unavailable',
                'message' => 'Backup codes channel is not available.',
            ], 400);
        }

        $result = $channel->regenerate($userId);

        return new WP_REST_Response([
            'success' => $result->success,
            'message' => $result->message,
            'data'    => $result->data,
        ], $result->success ? 200 : 400);
    }

    public function handleMe(WP_REST_Request $request): WP_REST_Response
    {
        $userId = get_current_user_id();
        $user = get_userdata($userId);

        $factors = $this->mfaManager->getUserFactors($userId);
        $enrolledFactors = [];

        foreach ($factors as $factor) {
            if ($factor->status !== ChannelStatus::Active) {
                continue;
            }

            $channel = $this->mfaManager->getChannel($factor->channelId);
            $enrolledFactors[] = array_merge(
                ['channel_id' => $factor->channelId],
                $channel ? $channel->getEnrollmentInfo($userId) : [],
            );
        }

        return new WP_REST_Response([
            'user' => [
                'id'               => $userId,
                'email'            => $user->user_email,
                'username'         => $user->user_login,
                'display_name'     => $user->display_name,
                'first_name'       => $user->first_name,
                'last_name'        => $user->last_name,
                'phone'            => get_user_meta($userId, 'wsms_phone', true) ?: null,
                'phone_verified'   => (bool) get_user_meta($userId, 'wsms_phone_verified', true),
                'email_verified'   => (bool) get_user_meta($userId, 'wsms_email_verified', true),
                'roles'            => $user->roles,
                'mfa_enabled'      => !empty($enrolledFactors),
                'enrolled_factors' => $enrolledFactors,
            ],
        ]);
    }
}
