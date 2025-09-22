<?php

namespace WP_SMS\Services\OTP\RestAPIEndpoints\Register;

use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;
use WP_Error;
use WP_SMS\Services\OTP\RestAPIEndpoints\Abstracts\RestAPIEndpointsAbstract;
use WP_SMS\Services\OTP\Helpers\UserHelper;
use WP_SMS\Services\OTP\Helpers\ChannelSettingsHelper;
use WP_SMS\Services\OTP\Models\MagicLinkModel;
use WP_SMS\Services\OTP\AuthChannel\MagicLink\MagicLinkService;
use WP_SMS\Services\OTP\AuthChannel\OTPMagicLink\OTPMagicLinkCombinedChannel;

class RegisterAddIdentifierAPIEndpoint extends RestAPIEndpointsAbstract
{
    public function registerRoutes(): void
    {
        register_rest_route('wpsms/v1', '/register/add-identifier', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'addIdentifier'],
            'permission_callback' => '__return_true',
            'args'                => $this->getAddIdentifierArgs(),
        ]);
    }

    private function getAddIdentifierArgs(): array
    {
        return [
            'flow_id' => [
                'required'          => true,
                'type'              => 'string',
                'description'       => 'Flow ID from the registration process',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'identifier' => [
                'required'          => true,
                'type'              => 'string',
                'description'       => 'New identifier to add (email or phone number)',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }

    /** ======================= Public entry (pipeline) ======================= */
    public function addIdentifier(WP_REST_Request $request)
    {
        try {
            $ctx = $this->initContext($request);

            $this->enforceRateLimit($ctx);
            $this->resolveUserByFlow($ctx);
            $this->ensureUserIsPending($ctx);

            $this->loadRequiredChannels($ctx);
            $this->resolveIdentifierType($ctx);
            $this->validateIdentifierRequired($ctx);
            $this->checkAlreadyVerifiedType($ctx);
            $this->ensureIdentifierAvailability($ctx);

            $this->loadChannelSettings($ctx);        // <- loads allow_otp / allow_magic / otp_digits, etc.
            $this->rotateFlowAndPersistIdentifier($ctx);

            $this->initArtifacts($ctx);              // <- OTP / Magic / Combined generation
            $this->sendArtifacts($ctx);              // <- send newly generated only

            $this->finalizeCountersAndLog($ctx);

            return $this->buildSuccess($ctx);

        } catch (WP_Error $we) {
            return $we;
        } catch (\Exception $e) {
            return $this->handleException($e, 'addIdentifier');
        }
    }

    /** ======================= Stage 0: Context ======================= */
    private function initContext(WP_REST_Request $request): array
    {
        $flowIdRaw     = (string) $request->get_param('flow_id');
        $identifierRaw = (string) $request->get_param('identifier');
        $ip            = $this->getClientIp($request);

        return [
            'req' => [
                'ip'                 => $ip,
                'flow_id_old'        => $flowIdRaw,
                'identifier_raw'     => $identifierRaw,
            ],
            'idn' => [
                'type'               => 'unknown',
                'norm'               => null,
                'masked'             => null,
            ],
            'user' => [
                'wp'                 => null,
                'verified_identifiers'=> [],
                'next_required'      => null,
            ],
            'required' => [
                'channels'           => [], // from ChannelSettingsHelper::getRequiredChannels()
            ],
            'settings' => [
                'allow_password'     => false,
                'allow_otp'          => false,
                'allow_magic'        => false,
                'otp_digits'         => 6,
                'use_combined'       => false,
            ],
            'flow' => [
                'new_flow_id'        => null,
            ],
            'artifacts' => [
                'otp_session'        => null,   // array map (flow_id, code, channel, expires_at)
                'magic_link'         => null,   // model/string as your service returns
                'is_new_otp'         => false,
                'is_new_magic'       => false,
            ],
            'send' => [
                'results'            => [],     // ['otp'=>[], 'magic_link'=>[], 'combined'=>[]]
                'primary_channel'    => null,
            ],
            'services' => [
                'magic_service'      => null,
                'combined_channel'   => null,
            ],
        ];
    }

    /** ======================= Stage 1: Rate limit ======================= */
    private function enforceRateLimit(array &$ctx): void
    {
        $rr = $this->checkRateLimits($ctx['req']['identifier_raw'], $ctx['req']['ip'], 'register_add_identifier');
        if (is_wp_error($rr)) {
            throw $rr;
        }
    }

    /** ======================= Stage 2: User by flow ======================= */
    private function resolveUserByFlow(array &$ctx): void
    {
        $user = UserHelper::getUserByFlowId($ctx['req']['flow_id_old']);
        if (!$user) {
            throw new \Exception(__('No user found for this flow', 'wp-sms'), 404);
        }
        $ctx['user']['wp'] = $user;
    }

    /** ======================= Stage 3: Ensure pending ======================= */
    private function ensureUserIsPending(array &$ctx): void
    {
        if (!UserHelper::isPendingUser($ctx['user']['wp']->ID)) {
            throw new \Exception(__('User has already been verified', 'wp-sms'), 409);
        }
    }

    /** ======================= Stage 4: Required identifiers policy ======================= */
    private function loadRequiredChannels(array &$ctx): void
    {
        $ctx['required']['channels'] = ChannelSettingsHelper::getRequiredChannels();
    }

    /** ======================= Stage 5: Identifier type & normalization ======================= */
    private function resolveIdentifierType(array &$ctx): void
    {
        $type = UserHelper::getIdentifierType($ctx['req']['identifier_raw']);
        if ($type === 'unknown') {
            throw new \Exception(__('Invalid identifier format. Provide a valid email or phone.', 'wp-sms'), 400);
        }
        $norm = $this->normalizeIdentifier($ctx['req']['identifier_raw'], $type);

        $ctx['idn']['type']   = $type;
        $ctx['idn']['norm']   = $norm;
        $ctx['idn']['masked'] = $this->maskIdentifier($ctx['req']['identifier_raw'], $type);
    }

    /** ======================= Stage 6: Validate this type is required ======================= */
    private function validateIdentifierRequired(array &$ctx): void
    {
        $req = $ctx['required']['channels'];
        $t   = $ctx['idn']['type'];

        if (!isset($req[$t]) || empty($req[$t]['required'])) {
            throw new \Exception(__('This identifier type is not required', 'wp-sms'), 400);
        }
    }

    /** ======================= Stage 7: Not already verified ======================= */
    private function checkAlreadyVerifiedType(array &$ctx): void
    {
        $verified = UserHelper::getVerifiedIdentifiers($ctx['user']['wp']->ID);
        $ctx['user']['verified_identifiers'] = is_array($verified) ? $verified : [];

        if (isset($ctx['user']['verified_identifiers'][$ctx['idn']['type']])) {
            throw new \Exception(__('This identifier type has already been verified', 'wp-sms'), 409);
        }
    }

    /** ======================= Stage 8: Ensure identifier free ======================= */
    private function ensureIdentifierAvailability(array &$ctx): void
    {
        $existing = UserHelper::getUserByIdentifier($ctx['idn']['norm']);
        if ($existing && (int) $existing->ID !== (int) $ctx['user']['wp']->ID) {
            throw new \Exception(__('This identifier is already in use by another user', 'wp-sms'), 409);
        }

        if ($ctx['idn']['type'] === 'email') {
            $existing = get_user_by('email', $ctx['idn']['norm']);
            if ($existing && (int) $existing->ID !== (int) $ctx['user']['wp']->ID) {
                throw new \Exception(__('This email is already in use by another user', 'wp-sms'), 409);
            }
        }
    }

    /** ======================= Stage 9: Load channel settings (OTP/Magic) ======================= */
    private function loadChannelSettings(array &$ctx): void
    {
        // Uses the same helper as in /register/start; parent provides $this->channelSettingsHelper
        $data = $this->channelSettingsHelper->getChannelData($ctx['idn']['type']);

        $ctx['settings']['allow_password'] = !empty($data['allow_password']);
        $ctx['settings']['allow_otp']      = !empty($data['allow_otp']);
        $ctx['settings']['allow_magic']    = !empty($data['allow_magic']);
        $ctx['settings']['otp_digits']     = isset($data['otp_digits']) ? (int) $data['otp_digits'] : 6;
        $ctx['settings']['use_combined']   = ($ctx['settings']['allow_otp'] && $ctx['settings']['allow_magic']);

        if ($ctx['settings']['allow_magic']) {
            $ctx['services']['magic_service'] = new MagicLinkService();
        }
        if ($ctx['settings']['use_combined']) {
            $ctx['services']['combined_channel'] = new OTPMagicLinkCombinedChannel();
        }

        // Ensure at least one method (OTP or Magic) is available for enrollment
        if (!$ctx['settings']['allow_otp'] && !$ctx['settings']['allow_magic']) {
            throw new \Exception(__('No authentication method is enabled for this identifier type. Please contact administrator.', 'wp-sms'), 400);
        }
    }

    /** ======================= Stage 10: Rotate flow & persist identifier ======================= */
    private function rotateFlowAndPersistIdentifier(array &$ctx): void
    {
        $newFlowId = uniqid('flow_', true);

        $ok = UserHelper::updateUserMeta($ctx['user']['wp']->ID, [
            'identifier'      => $ctx['idn']['norm'],
            'identifier_type' => $ctx['idn']['type'],
            'flow_id'         => $newFlowId,
        ]);

        if (!$ok) {
            throw new \Exception(__('Failed to update user identifier', 'wp-sms'), 500);
        }

        $ctx['flow']['new_flow_id'] = $newFlowId;
    }

    /** ======================= Stage 11: Init artifacts (OTP/Magic/Combined) ======================= */
    private function initArtifacts(array &$ctx): void
    {
        $flowId = $ctx['flow']['new_flow_id'];
        $idn    = $ctx['idn']['norm'];
        $type   = $ctx['idn']['type'];
        $digits = $ctx['settings']['otp_digits'];

        if ($ctx['settings']['use_combined']) {
            // New flow_id => no existing combined; just generate both
            $generated = $ctx['services']['combined_channel']->generate($flowId, $idn, $type, $digits);
            $ctx['artifacts']['otp_session']  = $this->mapOtpSessionForResponse($generated['otp_session']);
            $ctx['artifacts']['magic_link']   = $generated['magic_link'];
            $ctx['artifacts']['is_new_otp']   = true;
            $ctx['artifacts']['is_new_magic'] = true;
            return;
        }

        if ($ctx['settings']['allow_otp']) {
            $otp = $this->otpService->generate($flowId, $idn, $digits); // may throw on unexpired session
            $ctx['artifacts']['otp_session'] = $this->mapOtpSessionForResponse($otp);
            $ctx['artifacts']['is_new_otp']  = true;
        }

        if ($ctx['settings']['allow_magic']) {
            // new flow → there shouldn't be an existing link, but use model helper defensively
            $existingMagic = MagicLinkModel::getExistingValidLink($flowId);
            if ($existingMagic) {
                $ctx['artifacts']['magic_link']   = $existingMagic;
                $ctx['artifacts']['is_new_magic'] = false;
            } else {
                $ctx['artifacts']['magic_link']   = $ctx['services']['magic_service']->generate($flowId, $idn, $type);
                $ctx['artifacts']['is_new_magic'] = true;
            }
        }
    }

    /** ======================= Stage 12: Send artifacts ======================= */
    private function sendArtifacts(array &$ctx): void
    {
        if ($ctx['settings']['use_combined']) {
            $r = $ctx['services']['combined_channel']->sendCombined(
                $ctx['idn']['norm'],
                $ctx['artifacts']['otp_session'],
                $ctx['artifacts']['magic_link'],
                'register'
            );
            $ctx['send']['results']['combined'] = $r;
            if (empty($r['success'])) {
                throw new \Exception(!empty($r['error']) ? $r['error'] : __('Failed to send combined authentication message', 'wp-sms'), 500);
            }
            return;
        }

        // OTP only or alongside magic (but not combined)
        if ($ctx['settings']['allow_otp'] && !empty($ctx['artifacts']['is_new_otp'])) {
            $otpSend = $this->otpService->sendOTP(
                $ctx['idn']['norm'],
                $ctx['artifacts']['otp_session']['code'],
                $ctx['artifacts']['otp_session']['channel']
            );
            $ctx['send']['results']['otp'] = $otpSend;
            if (empty($otpSend['success'])) {
                throw new \Exception(!empty($otpSend['error']) ? $otpSend['error'] : __('Failed to send OTP', 'wp-sms'), 500);
            }
        }

        if ($ctx['settings']['allow_magic']) {
            // Send only if generated new; if reusing, mark success
            if (!empty($ctx['artifacts']['is_new_magic'])) {
                $magicSend = $ctx['services']['magic_service']->sendMagicLink($ctx['idn']['norm'], $ctx['artifacts']['magic_link']);
                $ctx['send']['results']['magic_link'] = $magicSend;
                if (empty($magicSend['success'])) {
                    throw new \Exception(!empty($magicSend['error']) ? $magicSend['error'] : __('Failed to send Magic Link', 'wp-sms'), 500);
                }
            } else {
                $channelUsed = ($ctx['idn']['type'] === 'email') ? 'email' : 'sms';
                $ctx['send']['results']['magic_link'] = [
                    'success'      => true,
                    'channel_used' => $channelUsed,
                    'reused'       => true,
                ];
            }
        }
    }

    /** ======================= Stage 13: Log + rate ======================= */
    private function finalizeCountersAndLog(array &$ctx): void
    {
        $primary = $this->derivePrimaryChannel($ctx['send']['results'], $ctx['idn']['type']);
        $ctx['send']['primary_channel'] = $primary;

        $this->logAuthEvent($ctx['flow']['new_flow_id'], 'register_add_identifier', 'allow', $primary, $ctx['req']['ip']);
        $this->incrementRateLimits($ctx['idn']['norm'], $ctx['req']['ip'], 'register_add_identifier');
    }

    /** ======================= Stage 14: Response ======================= */
    private function buildSuccess(array $ctx)
    {
        $nextReq = UserHelper::getNextRequiredIdentifier($ctx['user']['wp']->ID, $ctx['required']['channels']);

        $data = [
            'user_id'                 => $ctx['user']['wp']->ID,
            'flow_id'                 => $ctx['flow']['new_flow_id'],
            'identifier'              => $ctx['idn']['norm'],
            'identifier_type'         => $ctx['idn']['type'],
            'identifier_masked'       => $ctx['idn']['masked'],
            'channel_used'            => $ctx['send']['primary_channel'],
            'verified_identifiers'    => $ctx['user']['verified_identifiers'],
            'next_required_identifier'=> $nextReq,
            'next_step'               => $nextReq ? 'verify_next' : 'verify_current',
            'otp_enabled'             => (bool) $ctx['artifacts']['otp_session'],
            'magic_link_enabled'      => (bool) $ctx['artifacts']['magic_link'],
            'combined_enabled'        => (bool) $ctx['settings']['use_combined'],
        ];

        if (!empty($ctx['artifacts']['otp_session'])) {
            $data['otp_ttl_seconds'] = $this->getOtpTtlSeconds($ctx['artifacts']['otp_session']['expires_at']);
        }

        return $this->createSuccessResponse($data, __('Identifier added successfully. Please verify.', 'wp-sms'));
    }

    /** ======================= Helpers: normalization & masking ======================= */
    private function normalizeIdentifier(string $identifier, string $type): string
    {
        $normalized = trim($identifier);
        if ($type === 'email') {
            return strtolower($normalized);
        }
        // phone: simple normalization for hashing/delivery (NOT full E.164)
        $normalized = preg_replace('/[^\d\+]/', '', $normalized);
        if (strpos($normalized, '+') > 0) {
            $normalized = '+' . preg_replace('/\+/', '', $normalized);
        }
        return $normalized;
    }

    private function maskIdentifier(string $identifier, string $type): string
    {
        return ($type === 'email') ? $this->maskEmail($identifier) : $this->maskPhone($identifier);
    }

    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) return $email;
        $u = $parts[0]; $d = $parts[1];
        $mu = (strlen($u) <= 2) ? $u : substr($u, 0, 2) . str_repeat('*', strlen($u) - 2);
        return $mu . '@' . $d;
    }

    private function maskPhone(string $phone): string
    {
        $len = strlen($phone);
        if ($len <= 4) return str_repeat('*', $len);
        return substr($phone, 0, 3) . str_repeat('*', max(0, $len - 6)) . substr($phone, -3);
    }

    private function mapOtpSessionForResponse($otpSession): array
    {
        if (is_array($otpSession)) {
            return [
                'flow_id'    => isset($otpSession['flow_id']) ? $otpSession['flow_id'] : null,
                'code'       => isset($otpSession['code']) ? $otpSession['code'] : null,
                'channel'    => isset($otpSession['channel']) ? $otpSession['channel'] : null,
                'expires_at' => isset($otpSession['expires_at']) ? $otpSession['expires_at'] : null,
            ];
        }
        return [
            'flow_id'    => isset($otpSession->flow_id) ? $otpSession->flow_id : null,
            'code'       => isset($otpSession->code) ? $otpSession->code : null,
            'channel'    => isset($otpSession->channel) ? $otpSession->channel : null,
            'expires_at' => isset($otpSession->expires_at) ? $otpSession->expires_at : null,
        ];
    }

    private function getOtpTtlSeconds(string $expiresAt): int
    {
        $expires = strtotime($expiresAt);
        $now     = time();
        return max(0, $expires - $now);
    }

    private function derivePrimaryChannel(array $sendResults, string $identifierType): string
    {
        if (isset($sendResults['combined']['channel_used'])) return (string) $sendResults['combined']['channel_used'];
        if (isset($sendResults['otp']['channel_used'])) return (string) $sendResults['otp']['channel_used'];
        if (isset($sendResults['magic_link']['channel_used'])) return (string) $sendResults['magic_link']['channel_used'];
        return $identifierType; // fallback
    }
}
