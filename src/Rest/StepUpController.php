<?php

namespace WSms\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WSms\Audit\AuditLogger;
use WSms\Auth\AccountLockout;
use WSms\Auth\FreshnessManager;
use WSms\Auth\PolicyEngine;
use WSms\Auth\SettingsRepository;
use WSms\Auth\StepUpChallengeStore;
use WSms\Enums\EventType;
use WSms\Mfa\MfaManager;

defined('ABSPATH') || exit;

/**
 * Step-up re-authentication endpoints.
 *
 * Users hit `/auth/step-up/challenge` with a chosen method, receive a
 * challenge_id, then hit `/auth/step-up/verify` to prove identity. On
 * success, {@see FreshnessManager::markFresh()} stamps the current session
 * so subsequent sensitive operations pass the freshness gate.
 *
 * This controller never rotates the WP auth cookie — it only updates the
 * fresh-auth timestamp on the existing session. That is deliberate: it
 * lets the user re-auth without losing unsaved form state on the page they
 * came from, something Better Auth's "re-sign-in" pattern cannot do.
 */
class StepUpController extends Controller
{
    /** @var array<string, int> Method → usages for rate limiting tuples. */
    private const CHALLENGE_RATE_WINDOW = 60;
    private const CHALLENGE_RATE_MAX = 10;

    public function __construct(
        private FreshnessManager $freshnessManager,
        private StepUpChallengeStore $challengeStore,
        private PolicyEngine $policyEngine,
        private MfaManager $mfaManager,
        private AccountLockout $lockout,
        private AuditLogger $auditLogger,
        private SettingsRepository $settingsRepo,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/auth/step-up/challenge', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleChallenge'],
            'permission_callback' => 'is_user_logged_in',
            'args'                => [
                'method' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/step-up/verify', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleVerify'],
            'permission_callback' => 'is_user_logged_in',
            'args'                => [
                'challenge_id' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'response'     => ['required' => true],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/step-up/status', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handleStatus'],
            'permission_callback' => 'is_user_logged_in',
        ]);
    }

    public function handleStatus(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () {
            $userId = get_current_user_id();
            $window = $this->settingsRepo->getFreshAuthWindowSeconds();

            return $this->ok([
                'fresh'                    => $this->freshnessManager->isFresh($userId, $window),
                'window_seconds'           => $window,
                'current_freshness_age'    => $this->freshnessManager->getFreshAge($userId),
                'step_up_methods'          => $this->policyEngine->stepUpMethodsFor($userId),
            ]);
        });
    }

    public function handleChallenge(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $userId = get_current_user_id();
            $method = (string) $request->get_param('method');

            $allowed = $this->policyEngine->stepUpMethodsFor($userId);
            if (empty($allowed)) {
                $this->auditLogger->log(EventType::StepUpUnavailable, 'failure', $userId);
                return new WP_REST_Response([
                    'success' => false,
                    'error'   => 'step_up_unavailable',
                    'message' => __('Re-authentication is required but no step-up methods are available. Please contact support.', 'wp-sms'),
                ], 409);
            }

            if (!in_array($method, $allowed, true)) {
                return new WP_REST_Response([
                    'success' => false,
                    'error'   => 'invalid_method',
                    'message' => __('This step-up method is not available for your account.', 'wp-sms'),
                    'data'    => ['step_up_methods' => $allowed],
                ], 400);
            }

            $lockStatus = $this->lockout->isLocked($userId);
            if (!empty($lockStatus['locked'])) {
                return new WP_REST_Response([
                    'success' => false,
                    'error'   => 'account_locked',
                    'message' => __('Account is temporarily locked.', 'wp-sms'),
                    'data'    => ['retry_after' => $lockStatus['until']],
                ], 423);
            }

            $meta = [];

            // Password and TOTP/backup-code methods have no server-side
            // side effects; the user already has the credential in hand.
            if (in_array($method, ['totp', 'backup_codes'], true)) {
                $meta = ['requires_delivery' => false, 'channel_type' => $method];
            } elseif ($method === 'passkey' || str_starts_with($method, 'otp_')) {
                $channelId = $this->channelIdForMethod($method);
                $channel = $channelId ? $this->mfaManager->getChannel($channelId) : null;

                if (!$channel) {
                    return new WP_REST_Response([
                        'success' => false,
                        'error'   => 'invalid_method',
                        'message' => __('This step-up method is not available for your account.', 'wp-sms'),
                    ], 400);
                }

                $result = $channel->sendChallenge($userId);
                if (!$result->success) {
                    return new WP_REST_Response([
                        'success' => false,
                        'error'   => 'challenge_failed',
                        'message' => $result->message,
                    ], 400);
                }

                $meta = $result->meta;
            } elseif ($method !== 'password') {
                return new WP_REST_Response([
                    'success' => false,
                    'error'   => 'invalid_method',
                    'message' => __('This step-up method is not available for your account.', 'wp-sms'),
                ], 400);
            }

            $created = $this->challengeStore->create($userId, $method, $meta);

            if ($created === null) {
                // No session token — e.g. application password auth. Users
                // with cookie-less sessions cannot step up.
                return new WP_REST_Response([
                    'success' => false,
                    'error'   => 'no_session',
                    'message' => __('Step-up requires a browser session. Please sign in again.', 'wp-sms'),
                ], 401);
            }

            return new WP_REST_Response([
                'success'      => true,
                'challenge_id' => $created['challenge_id'],
                'method'       => $method,
                'meta'         => $meta,
            ]);
        });
    }

    public function handleVerify(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $userId = get_current_user_id();
            $challengeId = (string) $request->get_param('challenge_id');
            $response = $request->get_param('response');

            $challenge = $this->challengeStore->get($challengeId);

            if ($challenge === null || (int) ($challenge['user_id'] ?? 0) !== $userId) {
                return new WP_REST_Response([
                    'success' => false,
                    'error'   => 'challenge_expired',
                    'message' => __('This step-up challenge has expired. Please start a new one.', 'wp-sms'),
                ], 400);
            }

            $lockStatus = $this->lockout->isLocked($userId);
            if (!empty($lockStatus['locked'])) {
                $this->challengeStore->consume($challengeId);
                return new WP_REST_Response([
                    'success' => false,
                    'error'   => 'account_locked',
                    'message' => __('Account is temporarily locked.', 'wp-sms'),
                    'data'    => ['retry_after' => $lockStatus['until']],
                ], 423);
            }

            $method = (string) $challenge['method'];
            $verified = $this->verifyByMethod($userId, $method, $response);

            if (!$verified) {
                $attempts = $this->challengeStore->recordFailure($challengeId);
                $this->lockout->recordFailure($userId);
                $this->auditLogger->log(EventType::StepUpFailed, 'failure', $userId, [
                    'method'   => $method,
                    'attempts' => $attempts,
                ]);

                return new WP_REST_Response([
                    'success' => false,
                    'error'   => 'invalid_response',
                    'message' => __('Verification failed.', 'wp-sms'),
                    'data'    => [
                        'attempts'        => $attempts,
                        'max_attempts'    => StepUpChallengeStore::MAX_ATTEMPTS_PER_CHALLENGE,
                        'challenge_valid' => $attempts < StepUpChallengeStore::MAX_ATTEMPTS_PER_CHALLENGE,
                    ],
                ], 400);
            }

            $this->challengeStore->consume($challengeId);
            $this->lockout->reset($userId);
            $this->freshnessManager->markFresh($userId);

            $this->auditLogger->log(EventType::StepUpSucceeded, 'success', $userId, [
                'method' => $method,
            ]);

            return new WP_REST_Response([
                'success'                 => true,
                'fresh_auth_at'           => time(),
                'fresh_auth_window_seconds' => $this->settingsRepo->getFreshAuthWindowSeconds(),
            ]);
        });
    }

    /**
     * Dispatch verification to the right primitive for each method.
     */
    private function verifyByMethod(int $userId, string $method, mixed $response): bool
    {
        if ($method === 'password') {
            if (!is_array($response) || empty($response['password']) || !is_string($response['password'])) {
                return false;
            }
            $user = get_userdata($userId);
            return $user && wp_check_password($response['password'], $user->user_pass, $userId);
        }

        // All remaining methods delegate to an MFA channel's verify().
        // Passkey assertions arrive as a JSON object, everything else as a code.
        $channelId = $this->channelIdForMethod($method);
        if ($channelId === null) {
            return false;
        }

        $channel = $this->mfaManager->getChannel($channelId);
        if (!$channel) {
            return false;
        }

        $payload = $method === 'passkey'
            ? (is_string($response) ? $response : (string) wp_json_encode($response))
            : (string) (is_array($response) ? ($response['code'] ?? '') : $response);

        return (bool) $channel->verify($userId, $payload);
    }

    /**
     * Map a step-up method id to the MFA channel that handles it.
     *
     * Methods prefixed `otp_` route to the channel after the prefix (e.g.
     * `otp_email` → `email`). Channel-name methods route directly.
     */
    private function channelIdForMethod(string $method): ?string
    {
        if (str_starts_with($method, 'otp_')) {
            return substr($method, 4);
        }

        return in_array($method, ['passkey', 'totp', 'backup_codes'], true) ? $method : null;
    }
}
