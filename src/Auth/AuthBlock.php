<?php

namespace WSms\Auth;

defined('ABSPATH') || exit;

/**
 * Gutenberg block for the [wsms_auth] shortcode.
 *
 * Registers `wsms/auth-form` and delegates rendering to AuthShortcode::render().
 *
 * @since 8.0
 */
class AuthBlock
{
    public function __construct(
        private AuthShortcode $shortcode,
    ) {
    }

    public function registerHooks(): void
    {
        add_action('init', [$this, 'register']);
    }

    public function register(): void
    {
        register_block_type(
            WP_SMS_DIR . 'public/blocks/auth-form',
            ['render_callback' => [$this, 'render']],
        );
    }

    public function render(array $attributes): string
    {
        return $this->shortcode->render([
            'view' => $attributes['view'] ?? 'login',
            'id'   => $attributes['formSlug'] ?? '',
            'mode' => $attributes['mode'] ?? 'popup',
            'text' => $attributes['buttonText'] ?? 'Sign In',
        ]);
    }
}
