<?php

namespace WSms\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WSms\Auth\AuthOrchestrator;
use WSms\Auth\CaptchaGuard;
use WSms\Auth\PolicyEngine;
use WSms\Auth\RateLimiter;
use WSms\Auth\RegistrationFormRepository;
use WSms\Branding\BrandingRepository;
use WSms\Exception\NotFoundException;
use WSms\Exception\ValidationException;
use WSms\Mfa\MfaManager;
use WSms\PhoneRestriction\RestrictionSettings;
use WSms\Social\SocialAuthManager;

defined('ABSPATH') || exit;

class AuthController extends Controller
{
    public function __construct(
        private AuthOrchestrator $orchestrator,
        private RateLimiter $rateLimiter,
        private PolicyEngine $policy,
        private CaptchaGuard $captchaGuard,
        private SocialAuthManager $socialManager,
        private ?MfaManager $mfaManager = null,
        private ?RegistrationFormRepository $formRepository = null,
        private ?RestrictionSettings $restrictionSettings = null,
        private ?BrandingRepository $brandingRepo = null,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/auth/login', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleLogin'],
            'permission_callback' => '__return_true',
            'args'                => [
                'username' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'password' => ['required' => true, 'type' => 'string'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/login/passwordless', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handlePasswordless'],
            'permission_callback' => '__return_true',
            'args'                => [
                'method'     => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'identifier' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/verify', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleVerify'],
            'permission_callback' => '__return_true',
            'args'                => [
                'session_token' => ['required' => true, 'type' => 'string'],
                'code'            => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/verify-magic-link', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleVerifyMagicLink'],
            'permission_callback' => '__return_true',
            'args'                => [
                'token' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/resend', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleResend'],
            'permission_callback' => '__return_true',
            'args'                => [
                'session_token' => ['required' => true, 'type' => 'string'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/identify', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleIdentify'],
            'permission_callback' => '__return_true',
            'args'                => [
                'identifier' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/verification/complete', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleVerificationComplete'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/auth/config', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handleConfig'],
            'permission_callback' => '__return_true',
            'args'                => [
                'form' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);
    }

    public function handleLogin(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $rl = $this->rateLimiter->checkAction('login');

            if (!$rl['allowed']) {
                return $this->rateLimitedResponse($rl['retry_after']);
            }

            $captcha = $this->captchaGuard->verify($request, 'login');
            if ($captcha === false) {
                return CaptchaGuard::failedResponse();
            }

            $result = $this->orchestrator->loginWithPassword(
                $request->get_param('username'),
                $request->get_param('password'),
            );

            return $this->toAuthResponse($result);
        });
    }

    public function handlePasswordless(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $rl = $this->rateLimiter->checkAction('login_passwordless');

            if (!$rl['allowed']) {
                return $this->rateLimitedResponse($rl['retry_after']);
            }

            $captcha = $this->captchaGuard->verify($request, 'login');
            if ($captcha === false) {
                return CaptchaGuard::failedResponse();
            }

            $result = $this->orchestrator->loginPasswordless(
                $request->get_param('method'),
                $request->get_param('identifier'),
            );

            return $this->toAuthResponse($result);
        });
    }

    public function handleVerify(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $rl = $this->rateLimiter->checkAction('verify');

            if (!$rl['allowed']) {
                return $this->rateLimitedResponse($rl['retry_after']);
            }

            $result = $this->orchestrator->verifyPrimary(
                $request->get_param('session_token'),
                $request->get_param('code'),
            );

            return $this->toAuthResponse($result);
        });
    }

    public function handleVerifyMagicLink(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $rl = $this->rateLimiter->checkAction('verify');

            if (!$rl['allowed']) {
                return $this->rateLimitedResponse($rl['retry_after']);
            }

            $result = $this->orchestrator->verifyMagicLink(
                $request->get_param('token'),
            );

            return $this->toAuthResponse($result);
        });
    }

    public function handleResend(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $rl = $this->rateLimiter->checkAction('resend');

            if (!$rl['allowed']) {
                return $this->rateLimitedResponse($rl['retry_after']);
            }

            $result = $this->orchestrator->resendChallenge(
                $request->get_param('session_token'),
            );

            return $this->toAuthResponse($result);
        });
    }

    public function handleIdentify(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $rl = $this->rateLimiter->checkAction('identify');

            if (!$rl['allowed']) {
                return $this->rateLimitedResponse($rl['retry_after']);
            }

            $result = $this->orchestrator->identify(
                $request->get_param('identifier'),
            );

            return new WP_REST_Response($result->toArray(), 200);
        });
    }

    public function handleVerificationComplete(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $token = $request->get_header('X-Auth-Session')
                  ?? $request->get_header('X-Verification-Token');

            if (empty($token)) {
                throw ValidationException::field('token', __('Missing authentication token.', 'wp-sms'));
            }

            $result = $this->orchestrator->completeVerification($token);

            return $this->toAuthResponse($result);
        });
    }

    public function handleConfig(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $formSlug = $request->get_param('form');

            if ($formSlug && $this->formRepository) {
                $form = $this->formRepository->findBySlug($formSlug);
                if (!$form || $form->getStatus() !== 'active') {
                    throw NotFoundException::entity('Form', $formSlug);
                }

                return $this->buildFormConfig($form);
            }

            return $this->buildGlobalConfig();
        });
    }

    private function buildGlobalConfig(): WP_REST_Response
    {
        $config = $this->buildBaseConfig();

        $config['registration_fields'] = $this->policy->getEffectiveRegistrationFields();
        $config['registration_field_definitions'] = $this->policy->getRegistrationFieldDefinitions();
        $config = array_merge($config, $this->policy->getVerificationRequirements());

        $socialProviders = [];
        foreach ($this->socialManager->getEnabledProviders() as $p) {
            $socialProviders[] = [
                'id'            => $p->getId(),
                'name'          => $p->getName(),
                'icon'          => $p->getIconSvg(),
                'authorize_url' => rest_url('wsms/v1/auth/social/authorize/' . $p->getId()),
            ];
        }
        if (!empty($socialProviders)) {
            $config['social_providers'] = $socialProviders;
        }

        return new WP_REST_Response($config);
    }

    private function buildFormConfig(\WSms\Auth\RegistrationForm $form): WP_REST_Response
    {
        $config = $this->buildBaseConfig();

        $config['registration_fields'] = $this->policy->getFormEffectiveRegistrationFields($form);
        $config['registration_field_definitions'] = $this->policy->getFormRegistrationFieldDefinitions($form);
        $config['form_id'] = $form->getId();
        $config['form_name'] = $form->getName();
        $config['form_slug'] = $form->getSlug();
        $config = array_merge($config, $this->policy->getFormVerificationRequirements($form));

        if ($form->getRedirectUrl() !== '') {
            $config['form_redirect_url'] = $form->getRedirectUrl();
        }

        $formBranding = $form->getBrandingOverrides();
        if (!empty($formBranding)) {
            $config['branding'] = array_merge($config['branding'] ?? [], $formBranding);
        }

        return new WP_REST_Response($config);
    }

    private function buildBaseConfig(): array
    {
        $primaryMethods = $this->policy->getAvailablePrimaryMethods();

        $config = [
            'primary_methods'           => $primaryMethods,
            'method_details'            => $this->policy->getMethodDetails($primaryMethods),
            'mfa_enabled'               => !empty($this->policy->getAvailableMfaFactors()),
            'enabled_channels'          => $this->policy->getVerificationChannelKeys(),
            'base_url'                  => $this->policy->getSetting('auth_base_url', '/account'),
            'profile_field_definitions' => $this->policy->getProfileFieldDefinitions(),
        ];

        $captchaConfig = $this->captchaGuard->getPublicConfig();
        if ($captchaConfig) {
            $config['captcha'] = $captchaConfig;
        }

        if ($this->brandingRepo) {
            $config['branding'] = $this->brandingRepo->all();
        }

        $termsUrl = $this->policy->getSetting('terms_url', '');
        $privacyUrl = $this->policy->getSetting('privacy_url', '');
        if ($termsUrl || $privacyUrl) {
            $config['legal_links'] = [
                'terms_url'   => $termsUrl,
                'privacy_url' => $privacyUrl,
            ];
        }

        $trustedDevices = $this->policy->getSetting('trusted_devices', []);
        if (!empty($trustedDevices['enabled'])) {
            $config['trusted_devices'] = [
                'enabled' => true,
                'ttl'     => (int) ($trustedDevices['ttl'] ?? 2592000),
            ];
        }

        if ($this->restrictionSettings) {
            $config['phone_input'] = $this->restrictionSettings->getPhoneInputDisplayConfig('auth');
        }

        if (is_user_logged_in() && $this->mfaManager) {
            $userId = get_current_user_id();
            $config['enrollment_required'] = $this->policy->isMfaRequired($userId)
                && empty($this->mfaManager->getActiveMfaFactors($userId));
        }

        return $config;
    }
}
