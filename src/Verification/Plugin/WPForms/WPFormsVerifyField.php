<?php

namespace WSms\Verification\Plugin\WPForms;

use WSms\Verification\EnqueuesVerifyWidget;
use WSms\Verification\VerificationService;

defined('ABSPATH') || exit;

/**
 * Abstract base for WPForms verify fields (email & phone).
 *
 * Subclasses set $channel and field metadata in init() via initField().
 */
abstract class WPFormsVerifyField extends \WPForms_Field
{
    use EnqueuesVerifyWidget;

    /** @var string 'email' or 'phone' */
    protected string $channel;

    /** @var bool Whether frontend assets have already been enqueued (shared across subclasses). */
    public static bool $assetsEnqueued = false;

    public function __construct(protected VerificationService $verificationService)
    {
        parent::__construct();
    }

    public function init(): void
    {
        $this->initField();

        add_action('wpforms_frontend_js', [$this, 'enqueueAssets']);
    }

    abstract protected function initField(): void;

    public function field_options($field): void
    {
        $this->field_option('basic-options', $field, ['markup' => 'open']);
        $this->field_option('label', $field);
        $this->field_option('description', $field);
        $this->field_option('required', $field);
        $this->field_option('basic-options', $field, ['markup' => 'close']);

        $this->field_option('advanced-options', $field, ['markup' => 'open']);
        $this->field_option('size', $field);
        $this->field_option('placeholder', $field);
        $this->field_option('css', $field);
        $this->field_option('advanced-options', $field, ['markup' => 'close']);
    }

    public function field_preview($field): void
    {
        $this->field_preview_option('label', $field);

        $inputType   = $this->inputType();
        $placeholder = !empty($field['placeholder']) ? $field['placeholder'] : '';
        printf(
            '<input type="%s" placeholder="%s" class="primary-input" readonly>',
            esc_attr($inputType),
            esc_attr($placeholder),
        );
        echo '<div class="wsms-verify-preview-badge" style="margin-top:4px;font-size:12px;color:#2271b1;">'
            . esc_html__('WSMS verification enabled', 'wp-sms')
            . '</div>';

        $this->field_preview_option('description', $field);
    }

    public function field_display($field, $deprecated, $form_data): void
    {
        $primary  = $field['properties']['inputs']['primary'];
        $form_id  = absint($form_data['id']);
        $field_id = absint($field['id']);

        // Main input.
        printf(
            '<input type="%s" %s %s>',
            esc_attr($this->inputType()),
            wpforms_html_attributes($primary['id'], $primary['class'], $primary['data'], $primary['attr']),
            esc_attr($primary['required'] ?? ''),
        );

        // Widget container — the shared Preact widget mounts here.
        printf(
            '<span class="wsms-verify-widget-container" style="display:block" data-wsms-channel="%s" data-wsms-field="%d" data-wsms-form="%d"></span>',
            esc_attr($this->channel),
            $field_id,
            $form_id,
        );

        // Hidden session-token field.
        printf(
            '<input type="hidden" name="wpforms[wsms_verified][%d]" class="wsms-verified-flag" value="">',
            $field_id,
        );

        $this->field_display_error('primary', $field);
    }

    public function validate($field_id, $field_submit, $form_data): void
    {
        parent::validate($field_id, $field_submit, $form_data);

        if (empty($field_submit)) {
            return;
        }

        $form_id      = (int) $form_data['id'];
        $sessionToken = sanitize_text_field(wp_unslash($_POST['wpforms']['wsms_verified'][$field_id] ?? ''));

        if (empty($sessionToken) || !$this->verificationService->isVerified($this->channel, $field_submit, $sessionToken)) {
            $label = $this->channel === 'phone'
                ? esc_html__('phone number', 'wp-sms')
                : esc_html__('email address', 'wp-sms');

            wpforms()->obj('process')->errors[$form_id][$field_id] = sprintf(
                /* translators: %s: "email address" or "phone number" */
                esc_html__('Please verify your %s.', 'wp-sms'),
                $label,
            );
        }
    }

    public function format($field_id, $field_submit, $form_data): void
    {
        $name     = !empty($form_data['fields'][$field_id]['label']) ? $form_data['fields'][$field_id]['label'] : '';
        $sanitize = $this->channel === 'email' ? 'sanitize_email' : 'sanitize_text_field';

        wpforms()->obj('process')->fields[$field_id] = [
            'name'  => sanitize_text_field($name),
            'value' => $sanitize($field_submit),
            'id'    => absint($field_id),
            'type'  => $this->type,
        ];
    }

    public function enqueueAssets(): void
    {
        if (static::$assetsEnqueued) {
            return;
        }
        static::$assetsEnqueued = true;

        $this->enqueueVerifyWidget();

        wp_add_inline_script('wsms-verify-widget', <<<'JS'
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof wsmsVerify === 'undefined') return;
            document.querySelectorAll('.wsms-verify-widget-container').forEach(function(el) {
                var field = el.closest('.wpforms-field');
                if (!field) return;
                var input = field.querySelector('input[type="email"], input[type="tel"]');
                var flag = field.querySelector('.wsms-verified-flag');
                if (!input || !flag) return;
                var channel = el.dataset.wsmsChannel;
                var lastValue = '';

                input.addEventListener('blur', function() {
                    var value = input.value.trim();
                    if (!value || value === lastValue) return;
                    lastValue = value;
                    flag.value = '';
                    wsmsVerify.mount(el, {
                        channel: channel,
                        identifier: value,
                        onVerified: function(sessionToken) {
                            flag.value = sessionToken;
                        },
                    });
                });
            });
        });
        JS);
    }

    protected function inputType(): string
    {
        return match ($this->channel) {
            'phone' => 'tel',
            default => 'email',
        };
    }
}
