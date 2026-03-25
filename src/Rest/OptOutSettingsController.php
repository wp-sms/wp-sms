<?php

namespace WSms\Rest;

use WSms\Messaging\Inbound\KeywordMatcher;
use WSms\Messaging\Inbound\OptOutManager;

defined('ABSPATH') || exit;

class OptOutSettingsController extends Controller
{
    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/optout/settings', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'index'],
                'permission_callback' => [$this, 'canManage'],
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'update'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);
    }

    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () {
            $settings = get_option('wsms_optout_settings', []);
            $defaults = OptOutManager::getDefaults();

            return $this->ok([
                'settings' => array_merge($defaults, $settings),
                'defaults' => [
                    'stop_keywords'  => KeywordMatcher::getDefaultStopKeywords(),
                    'start_keywords' => KeywordMatcher::getDefaultStartKeywords(),
                    'help_keywords'  => KeywordMatcher::getDefaultHelpKeywords(),
                ],
            ]);
        });
    }

    public function update(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $body = $request->get_json_params();
            $defaults = OptOutManager::getDefaults();
            $current = get_option('wsms_optout_settings', []);

            $allowedKeys = array_keys($defaults);
            $update = $current;

            foreach ($allowedKeys as $key) {
                if (array_key_exists($key, $body)) {
                    $update[$key] = $this->sanitizeValue($key, $body[$key]);
                }
            }

            update_option('wsms_optout_settings', $update);

            return $this->ok([
                'settings' => array_merge($defaults, $update),
            ]);
        });
    }

    private function sanitizeValue(string $key, mixed $value): mixed
    {
        if (str_ends_with($key, '_enabled')) {
            return (bool) $value;
        }

        if (str_ends_with($key, '_keywords')) {
            if (!is_array($value)) {
                return [];
            }
            return array_values(array_filter(array_map('sanitize_text_field', $value)));
        }

        if (is_string($value)) {
            return sanitize_textarea_field($value);
        }

        return $value;
    }
}
