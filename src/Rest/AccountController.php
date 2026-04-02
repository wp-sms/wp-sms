<?php

namespace WSms\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WSms\Auth\AccountManager;
use WSms\Auth\AuthSession;
use WSms\Auth\AvatarManager;
use WSms\Auth\CaptchaGuard;
use WSms\Auth\ProfileFieldRegistry;
use WSms\Auth\RateLimiter;
use WSms\Auth\RegistrationFormRepository;
use WSms\Auth\SettingsRepository;
use WSms\Auth\ValueObjects\OperationResult;
use WSms\Enums\AuthErrorCode;
use WSms\Enums\SessionStage;
use WSms\Exception\ValidationException;
use WSms\Support\PhoneValidator;

defined('ABSPATH') || exit;

class AccountController extends Controller
{
    public function __construct(
        private AccountManager $accountManager,
        private RateLimiter $rateLimiter,
        private AuthSession $authSession,
        private CaptchaGuard $captchaGuard,
        private SettingsRepository $settingsRepo,
        private ?ProfileFieldRegistry $fieldRegistry = null,
        private ?AvatarManager $avatarManager = null,
        private ?RegistrationFormRepository $formRepository = null,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/auth/register', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleRegister'],
            'permission_callback' => '__return_true',
            'args'                => [
                'email'        => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_email'],
                'password'     => ['required' => false, 'type' => 'string'],
                'username'     => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_user'],
                'display_name' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'first_name'   => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'last_name'    => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'phone'        => PhoneValidator::restArg(),
                'form_id'      => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/forgot-password', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleForgotPassword'],
            'permission_callback' => '__return_true',
            'args'                => [
                'email' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_email'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/reset-password', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleResetPassword'],
            'permission_callback' => '__return_true',
            'args'                => [
                'token'    => ['required' => true, 'type' => 'string'],
                'password' => ['required' => true, 'type' => 'string'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/verify-email', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleVerifyEmail'],
            'permission_callback' => '__return_true',
            'args'                => [
                'token' => ['required' => true, 'type' => 'string'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/profile', [
            'methods'             => 'PUT',
            'callback'            => [$this, 'handleUpdateProfile'],
            'permission_callback' => 'is_user_logged_in',
            'args'                => [
                'display_name' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'first_name'   => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'last_name'    => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'phone'        => PhoneValidator::restArg(),
                'email'        => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_email'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/password', [
            'methods'             => 'PUT',
            'callback'            => [$this, 'handleChangePassword'],
            'permission_callback' => 'is_user_logged_in',
            'args'                => [
                'current_password' => ['required' => false, 'type' => 'string', 'default' => null],
                'new_password'     => ['required' => true, 'type' => 'string'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/logout', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleLogout'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        // --- Generic registration verification endpoints ---
        $verifyArgs = [
            'code' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
        ];

        register_rest_route(self::NAMESPACE, '/auth/register/verify/(?P<channel>[a-z_]+)', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleRegisterVerifyChannel'],
            'permission_callback' => '__return_true',
            'args'                => $verifyArgs,
        ]);

        register_rest_route(self::NAMESPACE, '/auth/register/resend/(?P<channel>[a-z_]+)', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleRegisterResendChannel'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/auth/register/status', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handleVerificationStatus'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/auth/profile/pending-change/(?P<channel>[a-z_]+)', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'handleCancelPendingChange'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        // --- Generic profile verification endpoints ---
        register_rest_route(self::NAMESPACE, '/auth/profile/send-verification/(?P<channel>[a-z_]+)', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleProfileSendVerification'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        register_rest_route(self::NAMESPACE, '/auth/profile/verify/(?P<channel>[a-z_]+)', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleProfileVerifyChannel'],
            'permission_callback' => 'is_user_logged_in',
            'args'                => $verifyArgs,
        ]);

        register_rest_route(self::NAMESPACE, '/auth/profile/avatar', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'handleUploadAvatar'],
                'permission_callback' => 'is_user_logged_in',
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [$this, 'handleDeleteAvatar'],
                'permission_callback' => 'is_user_logged_in',
            ],
        ]);

    }

    // --- Registration ---

    public function handleRegister(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $rl = $this->rateLimiter->checkAction('register');

            if (!$rl['allowed']) {
                return $this->rateLimitedResponse($rl['retry_after']);
            }

            $captcha = $this->captchaGuard->verify($request, 'register');
            if ($captcha === false) {
                return CaptchaGuard::failedResponse();
            }

            // Resolve registration form if specified.
            $formId = $request->get_param('form_id');
            $form = null;

            if ($formId && $this->formRepository) {
                $form = $this->formRepository->findBySlug($formId)
                     ?? $this->formRepository->find($formId);

                if (!$form || $form->getStatus() !== 'active') {
                    throw ValidationException::field('form_id', __('The specified registration form is not available.', 'wp-sms'));
                }
            }

            // Formless registration requires auto_create_users to be enabled.
            // Form-based registration is always allowed — the admin created the form intentionally.
            if (!$form && !$this->settingsRepo->get('auto_create_users')) {
                return new WP_REST_Response(
                    OperationResult::fail(AuthErrorCode::RegistrationDisabled, __('Registration is not available.', 'wp-sms'))->toArray(),
                    403,
                );
            }

            $data = [
                'email'        => $request->get_param('email'),
                'password'     => $request->get_param('password'),
                'username'     => $request->get_param('username'),
                'display_name' => $request->get_param('display_name'),
                'first_name'   => $request->get_param('first_name'),
                'last_name'    => $request->get_param('last_name'),
                'phone'        => $request->get_param('phone'),
            ];

            // Include custom field values from request body.
            if ($this->fieldRegistry) {
                foreach ($this->fieldRegistry->getCustomFields() as $field) {
                    $value = $request->get_param($field->id);
                    if ($value !== null) {
                        $data[$field->id] = $value;
                    }
                }
            }

            $result = $this->accountManager->registerUser($data, false, $form);

            return new WP_REST_Response($result->toArray(), $result->success ? 201 : 400);
        });
    }

    // --- Password ---

    public function handleForgotPassword(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $rl = $this->rateLimiter->checkAction('forgot_password');

            if (!$rl['allowed']) {
                return $this->rateLimitedResponse($rl['retry_after']);
            }

            $captcha = $this->captchaGuard->verify($request, 'forgot_password');
            if ($captcha === false) {
                return CaptchaGuard::failedResponse();
            }

            $this->accountManager->initiatePasswordReset($request->get_param('email'));

            return $this->ok(['message' => __('If that email exists, a reset link has been sent.', 'wp-sms')]);
        });
    }

    public function handleResetPassword(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $rl = $this->rateLimiter->checkAction('reset_password');

            if (!$rl['allowed']) {
                return $this->rateLimitedResponse($rl['retry_after']);
            }

            $result = $this->accountManager->completePasswordReset(
                $request->get_param('token'),
                $request->get_param('password'),
            );

            return new WP_REST_Response($result->toArray(), $result->success ? 200 : 400);
        });
    }

    public function handleVerifyEmail(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $rl = $this->rateLimiter->checkAction('verify_email');

            if (!$rl['allowed']) {
                return $this->rateLimitedResponse($rl['retry_after']);
            }

            $result = $this->accountManager->verifyEmail($request->get_param('token'));

            return new WP_REST_Response($result->toArray(), $result->success ? 200 : 400);
        });
    }

    // --- Profile ---

    public function handleUpdateProfile(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $data = array_filter([
                'display_name' => $request->get_param('display_name'),
                'first_name'   => $request->get_param('first_name'),
                'last_name'    => $request->get_param('last_name'),
                'phone'        => $request->get_param('phone'),
                'email'        => $request->get_param('email'),
            ], fn($v) => $v !== null);

            // Include custom field values from request body.
            if ($this->fieldRegistry) {
                foreach ($this->fieldRegistry->getFieldsForContext('profile') as $field) {
                    if ($field->isSystem()) {
                        continue;
                    }
                    $value = $request->get_param($field->id);
                    if ($value !== null) {
                        $data[$field->id] = $value;
                    }
                }
            }

            $result = $this->accountManager->updateProfile(get_current_user_id(), $data);

            return new WP_REST_Response($result->toArray(), $result->success ? 200 : 400);
        });
    }

    public function handleChangePassword(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $rl = $this->rateLimiter->checkAction('change_password');

            if (!$rl['allowed']) {
                return $this->rateLimitedResponse($rl['retry_after']);
            }

            $result = $this->accountManager->changePassword(
                get_current_user_id(),
                $request->get_param('current_password'),
                $request->get_param('new_password'),
            );

            return new WP_REST_Response($result->toArray(), $result->success ? 200 : 400);
        });
    }

    public function handleLogout(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () {
            $this->accountManager->logout();

            return $this->ok(['message' => __('Logged out successfully.', 'wp-sms')]);
        });
    }

    // --- Generic registration verification ---

    public function handleRegisterVerifyChannel(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $channel = $request->get_param('channel');
            $rl = $this->rateLimiter->check("register_verify_{$channel}", 10, 60);

            if (!$rl['allowed']) {
                return $this->rateLimitedResponse($rl['retry_after']);
            }

            $session = $this->validateVerificationToken($request);

            if (!$session) {
                return $this->invalidTokenResponse();
            }

            $result = $this->accountManager->verifyChannelOtp($session['user_id'], $channel, $request->get_param('code'));

            return new WP_REST_Response($result->toArray(), $result->success ? 200 : 400);
        });
    }

    public function handleRegisterResendChannel(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $channel = $request->get_param('channel');
            $rl = $this->rateLimiter->check("register_resend_{$channel}", 3, 60);

            if (!$rl['allowed']) {
                return $this->rateLimitedResponse($rl['retry_after']);
            }

            $session = $this->validateVerificationToken($request);

            if (!$session) {
                return $this->invalidTokenResponse();
            }

            $result = $this->accountManager->resendVerification($session['user_id'], $channel);

            return new WP_REST_Response($result->toArray(), $result->success ? 200 : 400);
        });
    }

    // --- Pending change cancellation ---

    public function handleCancelPendingChange(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $channel = $request->get_param('channel');

            if (!in_array($channel, ['phone', 'email'], true)) {
                throw ValidationException::field('channel', __('Invalid channel.', 'wp-sms'));
            }

            $this->accountManager->cancelPendingChange(get_current_user_id(), $channel);

            return $this->ok(['message' => __('Pending change cancelled.', 'wp-sms')]);
        });
    }

    // --- Generic profile verification ---

    public function handleProfileSendVerification(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $channel = $request->get_param('channel');
            $rl = $this->rateLimiter->check("profile_send_{$channel}", 3, 60);

            if (!$rl['allowed']) {
                return $this->rateLimitedResponse($rl['retry_after']);
            }

            $this->accountManager->sendVerificationChallenge(get_current_user_id(), $channel);

            return $this->ok(['message' => __('Verification sent.', 'wp-sms')]);
        });
    }

    public function handleProfileVerifyChannel(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $channel = $request->get_param('channel');
            $rl = $this->rateLimiter->check("profile_verify_{$channel}", 10, 60);

            if (!$rl['allowed']) {
                return $this->rateLimitedResponse($rl['retry_after']);
            }

            $result = $this->accountManager->verifyChannelOtp(
                get_current_user_id(),
                $channel,
                $request->get_param('code'),
            );

            return new WP_REST_Response($result->toArray(), $result->success ? 200 : 400);
        });
    }

    public function handleVerificationStatus(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $rl = $this->rateLimiter->check('register_status', 20, 60);

            if (!$rl['allowed']) {
                return $this->rateLimitedResponse($rl['retry_after']);
            }

            $session = $this->validateVerificationToken($request);

            if (!$session) {
                return $this->invalidTokenResponse();
            }

            $result = $this->accountManager->getVerificationStatus($session['user_id']);

            return $this->ok($result);
        });
    }

    // --- Avatar ---

    public function handleUploadAvatar(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            if (!$this->avatarManager) {
                return new WP_REST_Response([
                    'success' => false,
                    'error'   => 'unavailable',
                    'message' => __('Avatar uploads not available.', 'wp-sms'),
                ], 500);
            }

            $files = $request->get_file_params();
            $file = $files['avatar'] ?? null;

            if (!$file) {
                throw ValidationException::field('avatar', __('No avatar file provided.', 'wp-sms'));
            }

            $result = $this->avatarManager->uploadAvatar(get_current_user_id(), $file);

            return new WP_REST_Response($result, $result['success'] ? 200 : 400);
        });
    }

    public function handleDeleteAvatar(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () {
            if (!$this->avatarManager) {
                return new WP_REST_Response([
                    'success' => false,
                    'error'   => 'unavailable',
                    'message' => __('Avatar management not available.', 'wp-sms'),
                ], 500);
            }

            $this->avatarManager->deleteAvatar(get_current_user_id());

            return $this->ok(['message' => __('Avatar removed.', 'wp-sms')]);
        });
    }

    // --- Shared helpers ---

    private function invalidTokenResponse(): WP_REST_Response
    {
        return new WP_REST_Response([
            'success' => false,
            'error'   => AuthErrorCode::InvalidToken->value,
            'message' => __('Invalid or expired verification token.', 'wp-sms'),
        ], AuthErrorCode::InvalidToken->httpStatus());
    }

    private function validateVerificationToken(WP_REST_Request $request): ?array
    {
        $token = $request->get_header('X-Auth-Session')
              ?? $request->get_header('X-Verification-Token')
              ?? $request->get_header('X-Registration-Token');

        if (empty($token)) {
            return null;
        }

        $session = $this->authSession->validate($token);

        if (!$session || !in_array($session['stage'] ?? '', [SessionStage::RegistrationVerify->value, SessionStage::VerificationPending->value], true)) {
            return null;
        }

        return $session;
    }
}
