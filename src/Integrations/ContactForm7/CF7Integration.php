<?php

namespace WSms\Integrations\ContactForm7;

use WSms\Verification\EnqueuesVerifyWidget;
use WSms\Verification\VerificationService;

defined('ABSPATH') || exit;

class CF7Integration
{
    use EnqueuesVerifyWidget;

    public function __construct(private VerificationService $verificationService)
    {
    }

    public function registerHooks(): void
    {
        add_action('wpcf7_init', [$this, 'registerFormTags']);
        add_action('wpcf7_admin_init', [$this, 'registerTagGenerators']);

        add_filter('wpcf7_validate_wsms_verify_email', [$this, 'validateEmail'], 10, 2);
        add_filter('wpcf7_validate_wsms_verify_email*', [$this, 'validateEmail'], 10, 2);
        add_filter('wpcf7_validate_wsms_verify_phone', [$this, 'validatePhone'], 10, 2);
        add_filter('wpcf7_validate_wsms_verify_phone*', [$this, 'validatePhone'], 10, 2);

        add_filter('wpcf7_messages', [$this, 'registerMessages']);
        add_action('wpcf7_swv_create_schema', [$this, 'registerValidationRules'], 10, 2);

        add_action('wpcf7_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function registerFormTags(): void
    {
        wpcf7_add_form_tag(
            ['wsms_verify_email', 'wsms_verify_email*'],
            [$this, 'renderEmailTag'],
            ['name-attr' => true],
        );

        wpcf7_add_form_tag(
            ['wsms_verify_phone', 'wsms_verify_phone*'],
            [$this, 'renderPhoneTag'],
            ['name-attr' => true],
        );
    }

    /**
     * Register tag generator buttons in the CF7 form editor toolbar.
     */
    public function registerTagGenerators(): void
    {
        if (!class_exists('WPCF7_TagGenerator')) {
            return;
        }

        $tagGenerator = \WPCF7_TagGenerator::get_instance();

        $tagGenerator->add(
            'wsms_verify_email',
            __('WSMS: verify email', 'wp-sms'),
            [$this, 'tagGeneratorVerifyEmail'],
            ['version' => '2'],
        );

        $tagGenerator->add(
            'wsms_verify_phone',
            __('WSMS: verify phone', 'wp-sms'),
            [$this, 'tagGeneratorVerifyPhone'],
            ['version' => '2'],
        );
    }

    /**
     * Register custom validation messages in the CF7 Messages tab.
     */
    public function registerMessages(array $messages): array
    {
        $messages['wsms_verify_required'] = [
            'description' => __('Email/phone verification field is empty', 'wp-sms'),
            'default'     => __('This field is required.', 'wp-sms'),
        ];

        $messages['wsms_verify_not_verified'] = [
            'description' => __('Email/phone has not been verified', 'wp-sms'),
            'default'     => __('Please verify your %s.', 'wp-sms'),
        ];

        return $messages;
    }

    /**
     * Register SWV validation rules for client-side required check.
     */
    public function registerValidationRules($schema, $contact_form): void
    {
        $tags = $contact_form->scan_form_tags([
            'basetype' => ['wsms_verify_email', 'wsms_verify_phone'],
        ]);

        foreach ($tags as $tag) {
            if ($tag->is_required()) {
                $schema->add_rule(
                    wpcf7_swv_create_rule('required', [
                        'field' => $tag->name,
                        'error' => wpcf7_get_message('wsms_verify_required'),
                    ]),
                );
            }
        }
    }

    public function tagGeneratorVerifyEmail($contact_form, $options): void
    {
        $this->renderTagGeneratorPanel($options, 'wsms_verify_email', __('Email verification field', 'wp-sms'), __('Adds an email field with OTP verification (powered by WSMS). The visitor enters their email, receives a verification code, and must confirm it before the form can be submitted.', 'wp-sms'));
    }

    public function tagGeneratorVerifyPhone($contact_form, $options): void
    {
        $this->renderTagGeneratorPanel($options, 'wsms_verify_phone', __('Phone verification field', 'wp-sms'), __('Adds a phone field with SMS verification (powered by WSMS). The visitor enters their phone number, receives a verification code via SMS, and must confirm it before the form can be submitted.', 'wp-sms'));
    }

    public function renderEmailTag($tag): string
    {
        return $this->renderTag($tag, 'email');
    }

    public function renderPhoneTag($tag): string
    {
        return $this->renderTag($tag, 'phone');
    }

    public function validateEmail($result, $tag)
    {
        return $this->validateTag($result, $tag, 'email');
    }

    public function validatePhone($result, $tag)
    {
        return $this->validateTag($result, $tag, 'phone');
    }

    public function enqueueAssets(): void
    {
        $this->enqueueVerifyWidget();

        wp_add_inline_script('wsms-verify-widget', <<<'JS'
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof wsmsVerify === 'undefined') return;
            document.querySelectorAll('.wsms-verify-widget-container').forEach(function(el) {
                var wrap = el.closest('.wpcf7-form-control-wrap');
                if (!wrap) return;
                var input = wrap.querySelector('.wsms-verify-input');
                var flag = wrap.querySelector('.wsms-verified-flag');
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

    private function renderTag($tag, string $channel): string
    {
        $name = $tag->name;
        $inputType = $channel === 'phone' ? 'tel' : 'email';

        // Get validation error from previous submission (CF7 pattern, see modules/text.php:29).
        $validationError = function_exists('wpcf7_get_validation_error')
            ? wpcf7_get_validation_error($name)
            : '';

        // Follow CF7 pattern for placeholder handling (see modules/text.php:74-78).
        $value = (string) reset($tag->values);
        $placeholder = '';
        if ($tag->has_option('placeholder') || $tag->has_option('watermark')) {
            $placeholder = $value;
        }

        $class = 'wpcf7-form-control wsms-verify-input';
        if ($validationError) {
            $class .= ' wpcf7-not-valid';
        }

        $atts = '';
        $atts .= sprintf(' type="%s"', $inputType);
        $atts .= sprintf(' name="%s"', esc_attr($name));
        $atts .= sprintf(' class="%s"', esc_attr($class));

        if ($placeholder !== '') {
            $atts .= sprintf(' placeholder="%s"', esc_attr($placeholder));
        }

        if ($tag->is_required()) {
            $atts .= ' aria-required="true"';
        }

        if ($validationError) {
            $atts .= ' aria-invalid="true"';
            if (function_exists('wpcf7_get_validation_error_reference')) {
                $atts .= sprintf(' aria-describedby="%s"', wpcf7_get_validation_error_reference($name));
            }
        } else {
            $atts .= ' aria-invalid="false"';
        }

        return sprintf(
            '<span class="wpcf7-form-control-wrap wsms-verify-wrap" data-name="%1$s">'
            . '<input%2$s />%4$s'
            . '<span class="wsms-verify-widget-container" style="display:block" data-wsms-channel="%3$s" data-wsms-field="%1$s"></span>'
            . '<input type="hidden" name="wsms_verified_%1$s" class="wsms-verified-flag" value="" />'
            . '</span>',
            esc_attr($name),
            $atts,
            esc_attr($channel),
            $validationError,
        );
    }

    private function validateTag($result, $tag, string $channel)
    {
        $name = $tag->name;
        $identifier = isset($_POST[$name]) ? sanitize_text_field(wp_unslash($_POST[$name])) : '';
        $sessionToken = isset($_POST['wsms_verified_' . $name]) ? sanitize_text_field(wp_unslash($_POST['wsms_verified_' . $name])) : '';

        if ($tag->is_required() && empty($identifier)) {
            $message = function_exists('wpcf7_get_message')
                ? wpcf7_get_message('wsms_verify_required')
                : __('This field is required.', 'wp-sms');
            $result->invalidate($tag, $message);

            return $result;
        }

        if (!empty($identifier)) {
            if (empty($sessionToken) || !$this->verificationService->isVerified($channel, $identifier, $sessionToken)) {
                $verifyLabel = $channel === 'phone' ? __('phone number', 'wp-sms') : __('email address', 'wp-sms');
                $template = function_exists('wpcf7_get_message')
                    ? wpcf7_get_message('wsms_verify_not_verified')
                    : __('Please verify your %s.', 'wp-sms');
                $result->invalidate($tag, sprintf($template, $verifyLabel));
            }
        }

        return $result;
    }

    private function renderTagGeneratorPanel(array $options, string $basetype, string $title, string $description): void
    {
        $tgg = new \WPCF7_TagGeneratorGenerator($options['content']);

        ?>
        <header class="description-box">
            <h3><?php echo esc_html($title); ?></h3>
            <p><?php echo esc_html($description); ?></p>
        </header>

        <div class="control-box">
            <?php
            $tgg->print('field_type', [
                'with_required' => true,
                'select_options' => [$basetype => $title],
            ]);

            $tgg->print('field_name');
            $tgg->print('class_attr');
            $tgg->print('default_value', ['with_placeholder' => true]);
            ?>
        </div>

        <footer class="insert-box">
            <?php
            $tgg->print('insert_box_content');
            $tgg->print('mail_tag_tip');
            ?>
        </footer>
        <?php
    }
}
