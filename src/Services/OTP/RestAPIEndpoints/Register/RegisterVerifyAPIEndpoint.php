<?php

namespace WP_SMS\Services\OTP\RestAPIEndpoints\Register;

use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;
use WP_Error;
use WP_SMS\Services\OTP\RestAPIEndpoints\Abstracts\RestAPIEndpointsAbstract;
use WP_SMS\Services\OTP\Helpers\UserHelper;
use WP_SMS\Services\OTP\Helpers\ChannelSettingsHelper;
use WP_SMS\Services\OTP\AuthChannel\MagicLink\MagicLinkService;

class RegisterVerifyAPIEndpoint extends RestAPIEndpointsAbstract
{
    public function registerRoutes(): void
    {
        register_rest_route('wpsms/v1', '/register/verify', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'verifyRegister'],
            'permission_callback' => '__return_true',
            'args'                => $this->getVerifyRegisterArgs(),
        ]);
    }

    /**
     * Add Magic Link support: either otp_code OR magic_token must be provided.
     */
    private function getVerifyRegisterArgs(): array
    {
        return [
            'flow_id' => [
                'required'          => true,
                'type'              => 'string',
                'description'       => 'Flow ID from the registration process',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'otp_code' => [
                'required'          => false,
                'type'              => 'string',
                'description'       => 'OTP code received by the user',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'magic_token' => [
                'required'          => false,
                'type'              => 'string',
                'description'       => 'Magic link token received by the user',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }

    /** ======================= Public entry (pipeline) ======================= */
    public function verifyRegister(WP_REST_Request $request)
    {
        try {
            $ctx = $this->initContext($request);

            $this->enforceRateLimit($ctx);
            $this->resolvePendingUser($ctx);
            $this->ensureUserIsPending($ctx);

            $this->determineVerificationMethod($ctx);   // decides otp vs magic
            $this->validateCodeOrTokenProvided($ctx);

            $this->performVerification($ctx);           // validate via service
            $this->markIdentifierVerified($ctx);        // sets identifier as verified

            $this->evaluateCompletionStatus($ctx);      // all required verified?
            $this->logAndCounters($ctx);

            return $this->buildSuccessResponse($ctx);

        } catch (\Exception $e) {
            // Convert thrown exceptions to standard error response
            return $this->handleException($e, 'verifyRegister');
        }
    }

    /** ======================= Stage 0: Context ======================= */
    private function initContext(WP_REST_Request $request): array
    {
        $flowId     = (string) $request->get_param('flow_id');
        $otpCode    = (string) $request->get_param('otp_code');
        $magicToken = (string) $request->get_param('magic_token');
        $ip         = $this->getClientIp($request);

        return [
            'req' => [
                'ip'          => $ip,
                'flow_id'     => $flowId,
                'otp_code'    => $otpCode,
                'magic_token' => $magicToken,
            ],
            'user' => [
                'wp'          => null,
                'identifier'  => null,   // current identifier to verify
            ],
            'verify' => [
                'method'      => null,   // 'otp' | 'magic'
                'channel_used'=> null,
                'result'      => null,   // raw result if needed later
            ],
            'required' => [
                'channels'    => [],     // ChannelSettingsHelper::getRequiredChannels()
                'next'        => null,
                'all_verified'=> false,
            ],
            'response' => [
                'status'      => null,   // 'verified' | 'partial_verified'
                'next_step'   => null,   // 'complete' | 'verify_next'
                'message'     => null,
            ],
            'services' => [
                'magic'       => null,
            ],
        ];
    }

    /** ======================= Stage 1: Rate limit ======================= */
    private function enforceRateLimit(array &$ctx): void
    {
        $rr = $this->checkRateLimits($ctx['req']['flow_id'], $ctx['req']['ip'], 'register_verify');
        if (is_wp_error($rr)) {
            // Re-wrap as exception with same HTTP status code (default 429)
            $code = 429;
            if (method_exists($rr, 'get_error_data')) {
                $data = $rr->get_error_data();
                if (is_array($data) && isset($data['status'])) {
                    $code = (int) $data['status'];
                }
            }
            throw new \Exception($rr->get_error_message(), $code);
        }
    }

    /** ======================= Stage 2: Resolve pending user ======================= */
    private function resolvePendingUser(array &$ctx): void
    {
        $user = UserHelper::getUserByFlowId($ctx['req']['flow_id']);
        if (!$user) {
            throw new \Exception(__('No pending user found for this flow', 'wp-sms'), 404);
        }
        $ctx['user']['wp'] = $user;

        // Read current identifier from meta (kept from your original flow)
        $ctx['user']['identifier'] = get_user_meta($user->ID, 'wpsms_identifier', true);
        if (empty($ctx['user']['identifier'])) {
            // If not found, fail explicitly; frontend should restart identification step.
            throw new \Exception(__('No identifier found for this flow. Please restart verification.', 'wp-sms'), 400);
        }
    }

    /** ======================= Stage 3: Ensure user still pending ======================= */
    private function ensureUserIsPending(array &$ctx): void
    {
        if (!UserHelper::isPendingUser($ctx['user']['wp']->ID)) {
            throw new \Exception(__('User has already been verified', 'wp-sms'), 409);
        }
    }

    /** ======================= Stage 4: Determine verification method ======================= */
    private function determineVerificationMethod(array &$ctx): void
    {
        // If magic token provided, prefer magic; else OTP
        if (!empty($ctx['req']['magic_token'])) {
            $ctx['verify']['method'] = 'magic';
            $ctx['services']['magic'] = new MagicLinkService();
            return;
        }
        $ctx['verify']['method'] = 'otp';
    }

    /** ======================= Stage 5: Validate inputs presence ======================= */
    private function validateCodeOrTokenProvided(array &$ctx): void
    {
        if ($ctx['verify']['method'] === 'otp' && empty($ctx['req']['otp_code'])) {
            throw new \Exception(__('OTP code is required', 'wp-sms'), 400);
        }
        if ($ctx['verify']['method'] === 'magic' && empty($ctx['req']['magic_token'])) {
            throw new \Exception(__('Magic token is required', 'wp-sms'), 400);
        }
    }

    /** ======================= Stage 6: Perform verification ======================= */
    private function performVerification(array &$ctx): void
    {
        $verificationSuccess = false;

        if ($ctx['verify']['method'] === 'magic') {
            // Validate magic token against flow; service should check expiry & ownership
            $verificationSuccess = $ctx['services']['magic']->validate($ctx['req']['flow_id'], $ctx['req']['magic_token']);
            $ctx['verify']['channel_used'] = 'magic';
        } else {
            // OTP verification
            $verificationSuccess = $this->otpService->validate($ctx['req']['flow_id'], $ctx['req']['otp_code']);
            $ctx['verify']['channel_used'] = 'otp';
        }

        if (!$verificationSuccess) {
            // On failure: increment limiter and raise 400
            $this->incrementRateLimits($ctx['req']['flow_id'], $ctx['req']['ip'], 'register_verify');
            throw new \Exception(__('Invalid or expired verification code/link', 'wp-sms'), 400);
        }
    }

    /** ======================= Stage 7: Mark current identifier verified ======================= */
    private function markIdentifierVerified(array &$ctx): void
    {
        $markVerifiedSuccess = UserHelper::markIdentifierVerified($ctx['user']['wp']->ID, $ctx['user']['identifier']);
        if (!$markVerifiedSuccess) {
            throw new \Exception(__('Failed to mark identifier as verified', 'wp-sms'), 500);
        }
    }

    /** ======================= Stage 8: Evaluate completion (are all required verified?) ======================= */
    private function evaluateCompletionStatus(array &$ctx): void
    {
        $required = ChannelSettingsHelper::getRequiredChannels();
        $ctx['required']['channels'] = $required;

        $allVerified = UserHelper::areAllRequiredIdentifiersVerified($ctx['user']['wp']->ID, $required);
        $ctx['required']['all_verified'] = (bool) $allVerified;

        if ($allVerified) {
            $activated = UserHelper::activateUser($ctx['user']['wp']->ID);
            if (!$activated) {
                throw new \Exception(__('Failed to activate user', 'wp-sms'), 500);
            }
            $ctx['response']['status']    = 'verified';
            $ctx['response']['next_step'] = 'complete';
            $ctx['response']['message']   = __('Registration completed successfully', 'wp-sms');
            $ctx['required']['next']      = null;
            return;
        }

        // More identifiers still required
        $next = UserHelper::getNextRequiredIdentifier($ctx['user']['wp']->ID, $required);
        $ctx['required']['next']      = $next;
        $ctx['response']['status']    = 'partial_verified';
        $ctx['response']['next_step'] = 'verify_next';
        $ctx['response']['message']   = __('Identifier verified. Additional verification required.', 'wp-sms');
    }

    /** ======================= Stage 9: Logging + counters ======================= */
    private function logAndCounters(array &$ctx): void
    {
        $this->logAuthEvent(
            $ctx['req']['flow_id'],
            'register_verify',
            'allow',
            $ctx['verify']['channel_used'],
            $ctx['req']['ip']
        );
        $this->incrementRateLimits($ctx['req']['flow_id'], $ctx['req']['ip'], 'register_verify');
    }

    /** ======================= Stage 10: Response ======================= */
    private function buildSuccessResponse(array $ctx)
    {
        $verifiedIdentifiers = UserHelper::getVerifiedIdentifiers($ctx['user']['wp']->ID);

        $data = [
            'user_id'                 => $ctx['user']['wp']->ID,
            'flow_id'                 => $ctx['req']['flow_id'],
            'status'                  => $ctx['response']['status'],
            'next_step'               => $ctx['response']['next_step'], // 'complete' | 'verify_next'
            'next_required_identifier'=> $ctx['required']['next'],
            'verified_identifiers'    => $verifiedIdentifiers,
            'verified_via'            => $ctx['verify']['channel_used'], // 'otp' | 'magic'
        ];

        return $this->createSuccessResponse($data, $ctx['response']['message']);
    }
}
