<?php

namespace WP_SMS\Services\OTP\RestAPIEndpoints\Register;

use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;
use WP_Error;
use WP_SMS\Services\OTP\RestAPIEndpoints\Abstracts\RestAPIEndpointsAbstract;
use WP_SMS\Services\OTP\Helpers\UserHelper;
use WP_SMS\Services\OTP\Models\OtpSessionModel;
use WP_SMS\Services\OTP\Models\IdentifierModel;
use WP_SMS\Services\OTP\Models\MagicLinkModel;
use WP_SMS\Services\OTP\AuthChannel\MagicLink\MagicLinkService;
use WP_SMS\Services\OTP\AuthChannel\OTPMagicLink\OTPMagicLinkCombinedChannel;

class RegisterStartAPIEndpoint extends RestAPIEndpointsAbstract
{
    public function registerRoutes(): void
    {
        register_rest_route('wpsms/v1', '/register/start', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'startRegister'],
            'permission_callback' => '__return_true',
            'args'                => $this->getStartRegisterArgs(),
        ]);
    }

    private function getStartRegisterArgs(): array
    {
        return [
            'identifier' => [
                'required'          => true,
                'type'              => 'string',
                'description'       => 'Identifier for registration (phone number, email, etc.)',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => [$this, 'validateIdentifier'],
            ],
        ];
    }

    /** ---------- Public entry (pipeline) ---------- */
    public function startRegister(WP_REST_Request $request)
    {
        try {
            $ctx = $this->initContext($request);

            $this->enforceRateLimit($ctx);
            $this->enforceAvailability($ctx);
            $this->resolvePendingUser($ctx);
            $this->loadChannelSettings($ctx);
            $this->validateAuthMethodsAllowed($ctx);
            $this->initArtifacts($ctx);
            $this->sendArtifacts($ctx);
            $this->finalizeAndLog($ctx);

            return $this->buildSuccessResponse($ctx);

        } catch (WP_Error $we) {
            return $we;
        } catch (\Exception $e) {
            return $this->handleException($e, 'initRegister');
        }
    }

    /** ---------- Stage 0: Context ---------- */
    private function initContext(WP_REST_Request $request): array
    {
        $identifier     = (string) $request->get_param('identifier');
        $ip             = $this->getClientIp($request);
        $identifierType = UserHelper::getIdentifierType($identifier);
        $identifierNorm = $this->normalizeIdentifier($identifier, $identifierType);

        return [
            'req' => [
                'ip'               => $ip,
                'identifier_raw'   => $identifier,
                'identifier_type'  => $identifierType,
                'identifier_norm'  => $identifierNorm,
                'identifier_masked'=> $this->maskIdentifier($identifier, $identifierType),
            ],
            'user' => [
                'wp'     => null,
                'flowId' => null,
            ],
            'settings' => [
                'allow_password'      => false,
                'allow_otp'           => false,
                'allow_magic'         => false,
                'otp_digits'          => 6,
                'password_is_required'=> false,
                'allow_signin'        => false,
                'use_combined'        => false,
            ],
            'artifacts' => [
                'otp_session'      => null,
                'magic_link'       => null,
                'is_new_otp'       => false,
                'is_new_magic'     => false,
            ],
            'send' => [
                'results'        => [],
                'primary_channel'=> null,
            ],
            'services' => [
                'combined'       => null,
                'magic_service'  => null,
            ],
        ];
    }

    /** ---------- Stage 1: Rate limit ---------- */
    private function enforceRateLimit(array &$ctx): void
    {
        $rr = $this->checkRateLimits($ctx['req']['identifier_norm'], $ctx['req']['ip'], 'register_init');
        if (is_wp_error($rr)) {
            throw $rr;
        }
    }

    /** ---------- Stage 2: Availability checks ---------- */
    private function enforceAvailability(array &$ctx): void
    {
        if ($this->isIdentifierUnavailable($ctx['req']['identifier_norm'], $ctx['req']['identifier_type'])) {
            throw new \Exception(__('This identifier is already in use.', 'wp-sms'), 409);
        }

        if ($ctx['req']['identifier_type'] === 'email') {
            $existing = get_user_by('email', $ctx['req']['identifier_norm']);
            $isPending = $existing ? UserHelper::isPendingUser($existing->ID) : false;
            if ($existing && !$isPending) {
                throw new \Exception(__('An account with this email already exists. Please try logging in instead.', 'wp-sms'), 409);
            }
        }
    }

    /** ---------- Stage 3: Pending user ---------- */
    private function resolvePendingUser(array &$ctx): void
    {
        $user = $this->getOrCreatePendingUser($ctx['req']['identifier_norm']);
        if (!$user) {
            throw new \Exception(__('Unable to create or retrieve pending user', 'wp-sms'), 500);
        }
        $ctx['user']['wp']     = $user;
        $ctx['user']['flowId'] = UserHelper::getUserFlowId($user->ID);
    }

    /** ---------- Stage 4: Channel settings ---------- */
    private function loadChannelSettings(array &$ctx): void
    {
        $channelData = $this->channelSettingsHelper->getChannelData($ctx['req']['identifier_type']);
        $ctx['settings']['allow_password']       = !empty($channelData['allow_password']);
        $ctx['settings']['allow_otp']            = !empty($channelData['allow_otp']);
        $ctx['settings']['allow_magic']          = !empty($channelData['allow_magic']);
        $ctx['settings']['otp_digits']           = isset($channelData['otp_digits']) ? (int) $channelData['otp_digits'] : 6;
        $ctx['settings']['password_is_required'] = !empty($channelData['password_is_required']);
        $ctx['settings']['allow_signin']         = !empty($channelData['allow_signin']);
        $ctx['settings']['use_combined']         = ($ctx['settings']['allow_otp'] && $ctx['settings']['allow_magic']);

        if ($ctx['settings']['allow_magic']) {
            $ctx['services']['magic_service'] = new MagicLinkService();
        }
        if ($ctx['settings']['use_combined']) {
            $ctx['services']['combined'] = new OTPMagicLinkCombinedChannel();
        }
    }

    /** ---------- Stage 5: Validate ---------- */
    private function validateAuthMethodsAllowed(array &$ctx): void
    {
        if (!$ctx['settings']['allow_password'] && !$ctx['settings']['allow_otp'] && !$ctx['settings']['allow_magic']) {
            throw new \Exception(__('No authentication method is enabled for this identifier type. Please contact administrator.', 'wp-sms'), 400);
        }
    }

    /** ---------- Stage 6: Init artifacts (OTP/Magic) ---------- */
    private function initArtifacts(array &$ctx): void
    {
        $flowId = $ctx['user']['flowId'];
        $idn    = $ctx['req']['identifier_norm'];
        $type   = $ctx['req']['identifier_type'];
        $digits = $ctx['settings']['otp_digits'];

        if ($ctx['settings']['use_combined']) {
            $this->initArtifactsCombined($ctx, $flowId, $idn, $type, $digits);
            return;
        }

        if ($ctx['settings']['allow_otp']) {
            $existing = $this->getExistingOtpSession($flowId);
            if ($existing) {
                $ctx['artifacts']['otp_session'] = $existing;
            } else {
                $created = $this->createNewOtpSession($flowId, $idn, $digits);
                if (!$created) {
                    throw new \Exception(__('Unable to create OTP session', 'wp-sms'), 500);
                }
                $ctx['artifacts']['otp_session'] = $created;
                $ctx['artifacts']['is_new_otp']  = true;
            }
        }

        if ($ctx['settings']['allow_magic']) {
            $existingMagic = MagicLinkModel::getExistingValidLink($flowId);
            if ($existingMagic) {
                $ctx['artifacts']['magic_link']  = $existingMagic;
            } else {
                $ctx['artifacts']['magic_link']  = $ctx['services']['magic_service']->generate($flowId, $idn, $type);
                $ctx['artifacts']['is_new_magic']= true;
            }
        }
    }

    private function initArtifactsCombined(array &$ctx, string $flowId, string $identifierNorm, string $identifierType, int $otpDigits): void
    {
        $combined = $ctx['services']['combined'];
        if (!$combined->exists($flowId)) {
            $generated = $combined->generate($flowId, $identifierNorm, $identifierType, $otpDigits);
            $ctx['artifacts']['otp_session'] = $this->mapOtpSessionForResponse($generated['otp_session']);
            $ctx['artifacts']['magic_link']  = $generated['magic_link'];
            $ctx['artifacts']['is_new_otp']  = true;
            $ctx['artifacts']['is_new_magic']= true;
            return;
        }
        // Try reuse; regenerate any missing/expired
        $otp = $this->getExistingOtpSession($flowId);
        if (!$otp) {
            $regen = $combined->generate($flowId, $identifierNorm, $identifierType, $otpDigits);
            $ctx['artifacts']['otp_session'] = $this->mapOtpSessionForResponse($regen['otp_session']);
            $ctx['artifacts']['magic_link']  = $regen['magic_link'];
            $ctx['artifacts']['is_new_otp']  = true;
            $ctx['artifacts']['is_new_magic']= true;
            return;
        }

        $ctx['artifacts']['otp_session'] = $otp;

        $magic = $this->getExistingMagicLink($flowId);
        if (!$magic) {
            $ctx['artifacts']['magic_link']  = $ctx['services']['magic_service']->generate($flowId, $identifierNorm, $identifierType);
            $ctx['artifacts']['is_new_magic']= true;
        } else {
            $ctx['artifacts']['magic_link']  = $magic;
        }
    }

    /** ---------- Stage 7: Send artifacts ---------- */
    private function sendArtifacts(array &$ctx): void
    {
        if ($ctx['settings']['use_combined']) {
            $this->sendCombined($ctx);
            return;
        }

        // OTP
        if ($ctx['settings']['allow_otp']) {
            if (!empty($ctx['artifacts']['is_new_otp'])) {
                $otpSendResult = $this->otpService->sendOTP(
                    $ctx['req']['identifier_norm'],
                    $ctx['artifacts']['otp_session']['code'],
                    $ctx['artifacts']['otp_session']['channel']
                );
                $ctx['send']['results']['otp'] = $otpSendResult;
                if (empty($otpSendResult['success'])) {
                    throw new \Exception(!empty($otpSendResult['error']) ? $otpSendResult['error'] : __('Failed to send OTP', 'wp-sms'), 500);
                }
            } else {
                $ctx['send']['results']['otp'] = [
                    'success'      => true,
                    'channel_used' => isset($ctx['artifacts']['otp_session']['channel']) ? $ctx['artifacts']['otp_session']['channel'] : $ctx['req']['identifier_type'],
                    'reused'       => true,
                ];
            }
        }

        // Magic
        if ($ctx['settings']['allow_magic']) {
            if (!empty($ctx['artifacts']['is_new_magic'])) {
                $magicLinkSendResult = $ctx['services']['magic_service']->sendMagicLink($ctx['req']['identifier_norm'], $ctx['artifacts']['magic_link']);
                $ctx['send']['results']['magic_link'] = $magicLinkSendResult;
                if (empty($magicLinkSendResult['success'])) {
                    throw new \Exception(!empty($magicLinkSendResult['error']) ? $magicLinkSendResult['error'] : __('Failed to send Magic Link', 'wp-sms'), 500);
                }
            } else {
                $channelUsed = ($ctx['req']['identifier_type'] === 'email') ? 'email' : 'sms';
                $ctx['send']['results']['magic_link'] = [
                    'success'      => true,
                    'channel_used' => $channelUsed,
                    'reused'       => true,
                ];
            }
        }
    }

    private function sendCombined(array &$ctx): void
    {
        $isNewOtp   = !empty($ctx['artifacts']['is_new_otp']);
        $isNewMagic = !empty($ctx['artifacts']['is_new_magic']);

        if ($isNewOtp && $isNewMagic) {
            $combinedSendResult = $ctx['services']['combined']->sendCombined(
                $ctx['req']['identifier_norm'],
                $ctx['artifacts']['otp_session'],
                $ctx['artifacts']['magic_link'],
                'register'
            );
            $ctx['send']['results']['combined'] = $combinedSendResult;
            if (empty($combinedSendResult['success'])) {
                throw new \Exception(!empty($combinedSendResult['error']) ? $combinedSendResult['error'] : __('Failed to send combined authentication message', 'wp-sms'), 500);
            }
        } else {
            $ctx['send']['results']['combined'] = [
                'success'      => true,
                'channel_used' => isset($ctx['artifacts']['otp_session']['channel']) ? $ctx['artifacts']['otp_session']['channel'] : $ctx['req']['identifier_type'],
                'message_type' => 'combined',
                'reused'       => true,
            ];
        }
    }

    /** ---------- Stage 8: Log + rate ---------- */
    private function finalizeAndLog(array &$ctx): void
    {
        $ctx['send']['primary_channel'] = $this->derivePrimaryChannel($ctx['send']['results'], $ctx['req']['identifier_type']);
        $this->logAuthEvent($ctx['user']['flowId'], 'register_init', 'allow', $ctx['send']['primary_channel'], $ctx['req']['ip']);
        $this->incrementRateLimits($ctx['req']['identifier_norm'], $ctx['req']['ip'], 'register_init');
    }

    /** ---------- Stage 9: Response ---------- */
    private function buildSuccessResponse(array $ctx)
    {
        $isNewSession = (!empty($ctx['artifacts']['is_new_otp']) || !empty($ctx['artifacts']['is_new_magic']));
        $msg = $isNewSession
            ? __('Registration initiated successfully', 'wp-sms')
            : __('Registration reinitiated successfully', 'wp-sms');

        $data = [
            'flow_id'           => $ctx['user']['flowId'],
            'user_id'           => $ctx['user']['wp']->ID,
            'identifier_type'   => $ctx['req']['identifier_type'],
            'identifier_masked' => $ctx['req']['identifier_masked'],
            'next_step'         => 'verify',
            'otp_enabled'       => (bool) $ctx['artifacts']['otp_session'],
            'magic_link_enabled'=> (bool) $ctx['artifacts']['magic_link'],
            'combined_enabled'  => (bool) $ctx['settings']['use_combined'],
            'channel_used'      => $ctx['send']['primary_channel'],
        ];

        if (!empty($ctx['artifacts']['otp_session'])) {
            $data['otp_ttl_seconds'] = $this->getOtpTtlSeconds($ctx['artifacts']['otp_session']['expires_at']);
        }

        return $this->createSuccessResponse($data, $msg);
    }

    /** ---------- Validation ---------- */
    public function validateIdentifier($value, $request, $param)
    {
        if (empty($value)) {
            return new WP_Error('invalid_identifier', __('Identifier is required.', 'wp-sms'));
        }
        $identifierType = UserHelper::getIdentifierType($value);
        if ($identifierType === 'unknown') {
            return new WP_Error('invalid_identifier', __('Invalid identifier format. Please provide a valid email address or phone number.', 'wp-sms'));
        }
        return true;
    }

    /** ---------- Shared helpers (unchanged logic, cleaned) ---------- */
    private function isIdentifierUnavailable(string $identifierNorm, string $type): bool
    {
        $model  = new IdentifierModel();
        $hash   = md5($identifierNorm);
        $found  = $model->find([
            'value_hash' => $hash,
            'verified'   => true,
            // 'factor_type' => ($type === 'email' ? 'email' : 'phone'),
        ]);
        return !empty($found);
    }

    private function normalizeIdentifier(string $identifier, string $type): string
    {
        $normalized = trim($identifier);
        if ($type === 'email') {
            return strtolower($normalized);
        }
        $normalized = preg_replace('/[^\d\+]/', '', $normalized);
        if (strpos($normalized, '+') > 0) {
            $normalized = '+' . preg_replace('/\+/', '', $normalized);
        }
        return $normalized;
    }

    private function getOrCreatePendingUser(string $identifierNorm): ?\WP_User
    {
        $existing = UserHelper::getUserByIdentifier($identifierNorm);
        if ($existing && UserHelper::isPendingUser($existing->ID)) {
            return $existing;
        }
        $flowId = uniqid('flow_', true);
        return UserHelper::createPendingUser($identifierNorm, ['flow_id' => $flowId]);
    }

    private function getExistingOtpSession(string $flowId)
    {
        $existing = OtpSessionModel::getByFlowId($flowId);
        if ($existing && isset($existing['expires_at']) && strtotime($existing['expires_at']) > time()) {
            return $existing;
        }
        return null;
    }

    private function getExistingMagicLink(string $flowId)
    {
        $existing = MagicLinkModel::getExistingValidLink($flowId);
        return $existing ? $existing : null;
    }

    private function createNewOtpSession(string $flowId, string $identifierNorm, int $otpDigits)
    {
        try {
            $otp = $this->otpService->generate($flowId, $identifierNorm, $otpDigits);
            return $this->mapOtpSessionForResponse($otp);
        } catch (\Exception $e) {
            return null;
        }
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

    private function maskIdentifier(string $identifier, string $type): string
    {
        return ($type === 'email') ? $this->maskEmail($identifier) : $this->maskPhone($identifier);
    }

    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) return $email;
        $username = $parts[0]; $domain = $parts[1];
        $maskedUsername = (strlen($username) <= 2) ? $username : substr($username, 0, 2) . str_repeat('*', strlen($username) - 2);
        return $maskedUsername . '@' . $domain;
    }

    private function maskPhone(string $phone): string
    {
        $len = strlen($phone);
        if ($len <= 4) return str_repeat('*', $len);
        return substr($phone, 0, 3) . str_repeat('*', max(0, $len - 6)) . substr($phone, -3);
    }

    private function derivePrimaryChannel(array $sendResults, string $identifierType): string
    {
        if (isset($sendResults['combined']['channel_used'])) return (string) $sendResults['combined']['channel_used'];
        if (isset($sendResults['otp']['channel_used'])) return (string) $sendResults['otp']['channel_used'];
        if (isset($sendResults['magic_link']['channel_used'])) return (string) $sendResults['magic_link']['channel_used'];
        return $identifierType;
    }
}
