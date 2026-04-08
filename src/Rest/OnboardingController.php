<?php

namespace WSms\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WSms\Onboarding\OnboardingManager;

defined('ABSPATH') || exit;

class OnboardingController extends Controller
{
    public function __construct(
        private readonly OnboardingManager $onboarding,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/onboarding', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'getState'],
                'permission_callback' => [$this, 'canManage'],
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'updateState'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/onboarding/reset', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'resetWizard'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);
    }

    public function getState(): WP_REST_Response
    {
        return $this->handle(fn() => $this->ok($this->onboarding->getState()));
    }

    public function updateState(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            // get_json_params() returns null for empty / malformed bodies —
            // guard so updateState() doesn't TypeError on its array hint.
            $data = $request->get_json_params();
            $this->onboarding->updateState(is_array($data) ? $data : []);

            return $this->ok($this->onboarding->getState());
        });
    }

    public function resetWizard(): WP_REST_Response
    {
        return $this->handle(function () {
            $this->onboarding->resetWizard();

            return $this->ok($this->onboarding->getState());
        });
    }
}
