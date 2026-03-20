<?php

namespace WSms\MessagingButton;

defined('ABSPATH') || exit;

class MessagingButtonRenderer
{
    public function __construct(
        private readonly MessagingButtonSettings $settings,
        private readonly DisplayRuleEvaluator $displayRules,
    ) {
    }

    public function init(): void
    {
        // Bail early so we never register the wp_footer hook when the
        // widget is disabled — avoids per-page callback dispatch overhead.
        if (!$this->settings->isEnabled()) {
            return;
        }

        add_action('wp_footer', [$this, 'render']);
    }

    public function render(): void
    {
        $rules = $this->settings->get('display_rules', []);

        if (!$this->displayRules->shouldDisplay($rules)) {
            return;
        }

        $this->enqueueAssets();
    }

    private function enqueueAssets(): void
    {
        $baseUrl = plugin_dir_url(WP_SMS_MAIN_FILE) . 'public/auth/';
        $version = defined('WP_SMS_VERSION') ? WP_SMS_VERSION : '8.0.0';

        wp_enqueue_script(
            'wsms-messaging-button',
            $baseUrl . 'messaging-button.js',
            [],
            $version,
            true,
        );

        wp_localize_script('wsms-messaging-button', 'wsmsMessagingButtonConfig', [
            'restUrl' => rest_url('wsms/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'config' => $this->settings->getPublicConfig(),
        ]);
    }
}
