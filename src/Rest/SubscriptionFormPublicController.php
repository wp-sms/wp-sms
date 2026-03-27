<?php

namespace WSms\Rest;

use WSms\Auth\CaptchaGuard;
use WSms\Auth\RateLimiter;
use WSms\Exception\NotFoundException;
use WSms\SubscriptionForm\SubscriptionForm;
use WSms\SubscriptionForm\SubscriptionFormRepository;
use WSms\SubscriptionForm\SubscriptionHandler;

defined('ABSPATH') || exit;

class SubscriptionFormPublicController extends Controller
{
    public function __construct(
        private readonly SubscriptionFormRepository $formRepository,
        private readonly SubscriptionHandler $handler,
        private readonly RateLimiter $rateLimiter,
        private readonly CaptchaGuard $captchaGuard,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/subscribe/(?P<slug>[a-z0-9-]+)', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'handleSubmit'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'email'      => ['type' => 'string', 'sanitize_callback' => 'sanitize_email'],
                    'phone'      => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'first_name' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'last_name'  => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    '_hp'        => ['type' => 'string'],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/subscribe/(?P<slug>[a-z0-9-]+)/verify', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'handleVerify'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'code'          => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                    'session_token' => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                ],
            ],
        ]);
    }

    public function handleSubmit(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $rateCheck = $this->rateLimiter->check('subscription_form_submit', 5, 60);
            if (!$rateCheck['allowed']) {
                return new \WP_REST_Response([
                    'success'     => false,
                    'error'       => 'rate_limited',
                    'message'     => __('Too many submissions. Please try again later.', 'wp-sms'),
                    'retry_after' => $rateCheck['retry_after'],
                ], 429);
            }

            $captcha = $this->captchaGuard->verify($request, 'subscribe');
            if ($captcha === false) {
                return CaptchaGuard::failedResponse();
            }

            $form = $this->resolveActiveForm($request);

            $data = [
                'email'      => $request->get_param('email') ?? '',
                'phone'      => $request->get_param('phone') ?? '',
                'first_name' => $request->get_param('first_name') ?? '',
                'last_name'  => $request->get_param('last_name') ?? '',
                '_hp'        => $request->get_param('_hp') ?? '',
            ];

            $result = $this->handler->submit($form, $data);

            $statusCode = $result->success ? 200 : 422;
            if ($result->error === 'rate_limited') {
                $statusCode = 429;
            }

            return new \WP_REST_Response($result->toArray(), $statusCode);
        });
    }

    public function handleVerify(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $rateCheck = $this->rateLimiter->check('subscription_form_verify', 10, 60);
            if (!$rateCheck['allowed']) {
                return new \WP_REST_Response([
                    'success'     => false,
                    'error'       => 'rate_limited',
                    'message'     => __('Too many attempts. Please try again later.', 'wp-sms'),
                    'retry_after' => $rateCheck['retry_after'],
                ], 429);
            }

            $form = $this->resolveActiveForm($request);

            $result = $this->handler->verify($form, $request->get_param('session_token'), $request->get_param('code'));

            return new \WP_REST_Response($result->toArray(), $result->success ? 200 : 422);
        });
    }

    /**
     * Resolve an active form by slug or throw a NotFoundException.
     */
    private function resolveActiveForm(\WP_REST_Request $request): SubscriptionForm
    {
        $form = $this->formRepository->findBySlug($request->get_param('slug'));

        if (!$form || !$form->isActive()) {
            throw new NotFoundException(__('Form not found.', 'wp-sms'));
        }

        return $form;
    }
}
