<?php

namespace WSms\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WSms\Auth\SettingsRepository;
use WSms\Branding\BrandingRepository;
use WSms\Exception\ValidationException;

defined('ABSPATH') || exit;

class BrandingController extends Controller
{
    public function __construct(
        private BrandingRepository $brandingRepo,
        private SettingsRepository $settingsRepo,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/branding/settings', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'handleGetSettings'],
                'permission_callback' => [$this, 'canManage'],
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'handleUpdateSettings'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);
    }

    public function handleGetSettings(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () {
            return new WP_REST_Response([
                'success'      => true,
                'settings'     => $this->brandingRepo->all(),
                'auth_base_url' => $this->settingsRepo->get('auth_base_url', '/account'),
            ]);
        });
    }

    public function handleUpdateSettings(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $body = $request->get_params();

            $errors = $this->validate($body);

            if (!empty($errors)) {
                throw new ValidationException($errors);
            }

            $this->brandingRepo->update($body);

            return new WP_REST_Response([
                'success'      => true,
                'message'      => 'Branding settings updated.',
                'settings'     => $this->brandingRepo->all(),
                'auth_base_url' => $this->settingsRepo->get('auth_base_url', '/account'),
            ]);
        });
    }

    private function validate(array $branding): array
    {
        $errors = [];

        foreach (['primary_color', 'accent_color', 'text_color', 'error_color', 'background_color', 'split_panel_bg_color'] as $colorKey) {
            if (isset($branding[$colorKey]) && !preg_match('/^#[0-9a-fA-F]{6}$/', $branding[$colorKey])) {
                $errors[] = "{$colorKey} must be a valid hex color (e.g. #2563eb).";
            }
        }

        foreach (['logo_url', 'background_image_url', 'split_panel_bg_image_url'] as $urlKey) {
            if (!empty($branding[$urlKey]) && !filter_var($branding[$urlKey], FILTER_VALIDATE_URL)) {
                $errors[] = "{$urlKey} must be a valid URL.";
            }
        }

        if (isset($branding['color_mode']) && !in_array($branding['color_mode'], ['light', 'dark', 'auto'], true)) {
            $errors[] = 'color_mode must be one of: light, dark, auto.';
        }

        if (isset($branding['button_style']) && !in_array($branding['button_style'], ['filled', 'outline', 'ghost'], true)) {
            $errors[] = 'button_style must be one of: filled, outline, ghost.';
        }

        if (isset($branding['layout']) && !in_array($branding['layout'], ['centered', 'split'], true)) {
            $errors[] = 'layout must be "centered" or "split".';
        }

        if (isset($branding['logo_position']) && !in_array($branding['logo_position'], ['left', 'center', 'right', 'hidden'], true)) {
            $errors[] = 'logo_position must be one of: left, center, right, hidden.';
        }

        if (isset($branding['logo_size'])) {
            $v = (int) $branding['logo_size'];
            if ($v < 20 || $v > 80) {
                $errors[] = 'logo_size must be between 20 and 80.';
            }
        }

        if (isset($branding['social_position']) && !in_array($branding['social_position'], ['top', 'bottom'], true)) {
            $errors[] = 'social_position must be "top" or "bottom".';
        }

        if (isset($branding['border_radius'])) {
            $v = (int) $branding['border_radius'];
            if ($v < 0 || $v > 32) {
                $errors[] = 'border_radius must be between 0 and 32.';
            }
        }

        if (isset($branding['split_panel_position']) && !in_array($branding['split_panel_position'], ['left', 'right'], true)) {
            $errors[] = 'split_panel_position must be "left" or "right".';
        }

        foreach (['site_name', 'split_welcome_heading', 'split_subtitle'] as $textKey) {
            if (isset($branding[$textKey]) && !is_string($branding[$textKey])) {
                $errors[] = "{$textKey} must be a string.";
            }
        }

        return $errors;
    }
}
