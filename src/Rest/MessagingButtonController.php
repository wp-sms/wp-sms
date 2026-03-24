<?php

namespace WSms\Rest;

use WSms\Auth\RateLimiter;
use WSms\Exception\ValidationException;
use WSms\MessagingButton\MessageHandler;
use WSms\MessagingButton\MessagingButtonSettings;
use WSms\Messaging\Gateway\GatewayRegistry;
use WSms\Support\UserMeta;

defined('ABSPATH') || exit;

class MessagingButtonController extends Controller
{
    public function __construct(
        private readonly MessagingButtonSettings $settings,
        private readonly MessageHandler $messageHandler,
        private readonly GatewayRegistry $gatewayRegistry,
        private readonly RateLimiter $rateLimiter,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/messaging-button/message', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'handleMessage'],
                'permission_callback' => '__return_true',
                'args' => [
                    'name' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'email' => ['type' => 'string', 'sanitize_callback' => 'sanitize_email'],
                    'phone' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'message' => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_textarea_field'],
                    'page_url' => ['type' => 'string', 'sanitize_callback' => 'sanitize_url'],
                    'gdpr_consent' => ['type' => 'boolean'],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/messaging-button/config', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'handleGetConfig'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/messaging-button/settings', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'handleGetSettings'],
                'permission_callback' => [$this, 'canManage'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'handleUpdateSettings'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);
    }

    public function handleMessage(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $rateCheck = $this->rateLimiter->check('messaging_button_message', 5, 60);
            if (!$rateCheck['allowed']) {
                return new \WP_REST_Response([
                    'success' => false,
                    'error' => __('Too many messages. Please try again later.', 'wp-sms'),
                    'retry_after' => $rateCheck['retry_after'],
                ], 429);
            }

            if (!$this->settings->isEnabled()) {
                return new \WP_REST_Response([
                    'success' => false,
                    'error' => __('Messaging button is not enabled.', 'wp-sms'),
                ], 403);
            }

            // GDPR check
            $gdprSettings = $this->settings->get('gdpr', []);
            if (!empty($gdprSettings['enabled']) && empty($request->get_param('gdpr_consent'))) {
                throw ValidationException::field('gdpr_consent', __('You must accept the privacy policy to send a message.', 'wp-sms'));
            }

            $email = $request->get_param('email') ?? '';
            if ($email !== '' && !is_email($email)) {
                throw ValidationException::field('email', __('Please provide a valid email address.', 'wp-sms'));
            }

            $data = [
                'name' => $request->get_param('name') ?? '',
                'email' => $email,
                'phone' => $request->get_param('phone') ?? '',
                'message' => $request->get_param('message') ?? '',
                'page_url' => $request->get_param('page_url') ?? '',
            ];

            // Merge WP user data as fallback for logged-in users
            if (is_user_logged_in()) {
                $user = wp_get_current_user();
                $userName = UserMeta::displayName($user);
                $data['name'] = $data['name'] ?: $userName;
                $data['email'] = $data['email'] ?: $user->user_email;
                $data['phone'] = $data['phone'] ?: (get_user_meta($user->ID, UserMeta::PHONE, true) ?: '');
                $data['user_id'] = $user->ID;
            }

            $result = $this->messageHandler->handle($data);

            if (!$result['success']) {
                return new \WP_REST_Response($result, 422);
            }

            return $this->ok(['message' => __('Message sent successfully.', 'wp-sms')]);
        });
    }

    public function handleGetConfig(): \WP_REST_Response
    {
        return $this->handle(function () {
            return new \WP_REST_Response([
                'success' => true,
                'config' => $this->settings->getPublicConfig(),
                'available_channels' => array_keys($this->gatewayRegistry->getConfiguredChannels()),
            ]);
        });
    }

    public function handleGetSettings(): \WP_REST_Response
    {
        return $this->handle(function () {
            return new \WP_REST_Response([
                'success' => true,
                'settings' => $this->settings->all(),
                'wp_timezone' => wp_timezone_string(),
            ]);
        });
    }

    public function handleUpdateSettings(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $payload = $request->get_json_params();

            if (empty($payload) || !is_array($payload)) {
                throw ValidationException::field('settings', __('Invalid settings payload.', 'wp-sms'));
            }

            $this->settings->update($this->sanitizeSettings($payload));

            return new \WP_REST_Response([
                'success' => true,
                'settings' => $this->settings->all(),
                'message' => __('Settings saved.', 'wp-sms'),
            ]);
        });
    }

    private function sanitizeSettings(array $settings): array
    {
        $textFields = [
            'button.text', 'widget.title', 'widget.subtitle',
            'pages.welcome.greeting', 'pages.welcome.cta_label',
            'greeting_bubble.message', 'default_message',
            'business_hours.offline_message', 'gdpr.consent_text',
        ];
        $urlFields = ['gdpr.link_url'];
        $colorFields = ['button.primary_color', 'button.text_color'];
        $enumFields = [
            'button.position' => ['bottom-right', 'bottom-left'],
            'button.style' => ['icon-text', 'icon', 'text'],
            'button.attention' => ['none', 'pulse', 'bounce', 'badge'],
            'widget.theme' => ['light', 'dark', 'system'],
            'display_rules.visibility' => ['everyone', 'logged_in', 'logged_out'],
            'pages.contact_form.channel' => ['email', 'sms'],
        ];

        // Sanitize known text fields (not all strings — array_walk_recursive
        // would strip newlines from URL lists and double-sanitize URL/color fields)
        foreach ($textFields as $path) {
            $this->setNestedIfExists($settings, $path, 'sanitize_text_field');
        }

        // Booleans and integers pass through unchanged — they don't need
        // sanitize_text_field and are validated by WP REST schema types.

        // Team members: sanitize text fields and URLs
        if (isset($settings['team_members']) && is_array($settings['team_members'])) {
            foreach ($settings['team_members'] as &$member) {
                if (isset($member['name'])) {
                    $member['name'] = sanitize_text_field($member['name']);
                }
                if (isset($member['role'])) {
                    $member['role'] = sanitize_text_field($member['role']);
                }
                if (!empty($member['avatar_url'])) {
                    $member['avatar_url'] = esc_url_raw($member['avatar_url']);
                }
                if (isset($member['contact_methods']) && is_array($member['contact_methods'])) {
                    foreach ($member['contact_methods'] as &$method) {
                        if (isset($method['type'])) {
                            $method['type'] = sanitize_text_field($method['type']);
                        }
                        if (isset($method['value'])) {
                            $method['value'] = sanitize_text_field($method['value']);
                        }
                    }
                }
            }
        }

        // URL fields
        foreach ($urlFields as $path) {
            $this->setNestedIfExists($settings, $path, fn($v) => esc_url_raw($v));
        }

        // Color fields: validate hex
        foreach ($colorFields as $path) {
            $this->setNestedIfExists($settings, $path, function ($v) {
                return preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? $v : '#2563eb';
            });
        }

        // Enum fields: validate against allowed values
        foreach ($enumFields as $path => $allowed) {
            $this->setNestedIfExists($settings, $path, function ($v) use ($allowed) {
                return in_array($v, $allowed, true) ? $v : $allowed[0];
            });
        }

        // Resources links: sanitize text and URLs
        if (isset($settings['pages']['resources']['links']) && is_array($settings['pages']['resources']['links'])) {
            foreach ($settings['pages']['resources']['links'] as &$link) {
                if (isset($link['title'])) {
                    $link['title'] = sanitize_text_field($link['title']);
                }
                if (isset($link['description'])) {
                    $link['description'] = sanitize_text_field($link['description']);
                }
                if (!empty($link['url'])) {
                    $link['url'] = esc_url_raw($link['url']);
                }
            }
        }

        // URL pattern lists: sanitize each line individually (preserving list structure)
        $urlListFields = ['display_rules.include_urls', 'display_rules.exclude_urls'];
        foreach ($urlListFields as $path) {
            $this->sanitizeStringArrayIfExists($settings, $path);
        }

        // Notification recipients
        $this->sanitizeStringArrayIfExists($settings, 'pages.contact_form.notification_recipients');

        // Business hours schedule entries
        if (isset($settings['business_hours']['schedule']) && is_array($settings['business_hours']['schedule'])) {
            foreach ($settings['business_hours']['schedule'] as &$entry) {
                if (isset($entry['day'])) {
                    $entry['day'] = sanitize_text_field($entry['day']);
                }
                if (isset($entry['open'])) {
                    $entry['open'] = sanitize_text_field($entry['open']);
                }
                if (isset($entry['close'])) {
                    $entry['close'] = sanitize_text_field($entry['close']);
                }
            }
        }

        return $settings;
    }

    private function sanitizeStringArrayIfExists(array &$data, string $path): void
    {
        $keys = explode('.', $path);
        $ref = &$data;

        foreach ($keys as $i => $key) {
            if ($i === count($keys) - 1) {
                if (isset($ref[$key]) && is_array($ref[$key])) {
                    $ref[$key] = array_map('sanitize_text_field', $ref[$key]);
                }
                return;
            }
            if (!isset($ref[$key]) || !is_array($ref[$key])) {
                return;
            }
            $ref = &$ref[$key];
        }
    }

    private function setNestedIfExists(array &$data, string $path, callable $transform): void
    {
        $keys = explode('.', $path);
        $ref = &$data;

        foreach ($keys as $i => $key) {
            if ($i === count($keys) - 1) {
                if (isset($ref[$key]) && is_string($ref[$key])) {
                    $ref[$key] = $transform($ref[$key]);
                }
                return;
            }
            if (!isset($ref[$key]) || !is_array($ref[$key])) {
                return;
            }
            $ref = &$ref[$key];
        }
    }
}
