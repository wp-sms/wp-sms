<?php

namespace WSms\Verification\Plugin\ContactForm7;

use WSms\PhoneRestriction\RestrictionSettings;
use WSms\Verification\EnqueuesVerifyWidget;
use WSms\Verification\VerificationService;

defined('ABSPATH') || exit;

class CF7Integration
{
    use EnqueuesVerifyWidget;

    public function __construct(
        private VerificationService $verificationService,
        private RestrictionSettings $restrictionSettings,
    ) {
    }

    public function registerHooks(): void
    {
        add_action('wpcf7_init', [$this, 'registerFormTags']);
        add_action('wpcf7_admin_init', [$this, 'registerTagGenerators']);

        add_filter('wpcf7_validate_wsms_verify_email', [$this, 'validateEmail'], 10, 2);
        add_filter('wpcf7_validate_wsms_verify_email*', [$this, 'validateEmail'], 10, 2);
        add_filter('wpcf7_validate_wsms_phone', [$this, 'validatePhone'], 10, 2);
        add_filter('wpcf7_validate_wsms_phone*', [$this, 'validatePhone'], 10, 2);

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
            ['wsms_phone', 'wsms_phone*'],
            [$this, 'renderPhoneTag'],
            ['name-attr' => true],
        );
    }

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
            'wsms_phone',
            __('WSMS: Phone', 'wp-sms'),
            [$this, 'tagGeneratorPhone'],
            ['version' => '2'],
        );
    }

    public function registerMessages(array $messages): array
    {
        $messages['wsms_verify_required'] = [
            'description' => __('Email verification field is empty', 'wp-sms'),
            'default'     => __('This field is required.', 'wp-sms'),
        ];

        $messages['wsms_verify_email_not_verified'] = [
            'description' => __('Email address has not been verified', 'wp-sms'),
            'default'     => __('Please verify your email address.', 'wp-sms'),
        ];

        $messages['wsms_verify_phone_not_verified'] = [
            'description' => __('Phone number has not been verified', 'wp-sms'),
            'default'     => __('Please verify your phone number.', 'wp-sms'),
        ];

        $messages['wsms_phone_required'] = [
            'description' => __('Phone field is empty', 'wp-sms'),
            'default'     => __('This field is required.', 'wp-sms'),
        ];

        $messages['wsms_phone_invalid'] = [
            'description' => __('Phone number is not in valid format', 'wp-sms'),
            'default'     => __('Please enter a valid phone number.', 'wp-sms'),
        ];

        return $messages;
    }

    public function registerValidationRules($schema, $contact_form): void
    {
        $tags = $contact_form->scan_form_tags([
            'basetype' => ['wsms_verify_email', 'wsms_phone'],
        ]);

        foreach ($tags as $tag) {
            if ($tag->is_required()) {
                $messageKey = $tag->basetype === 'wsms_phone'
                    ? 'wsms_phone_required'
                    : 'wsms_verify_required';

                $schema->add_rule(
                    wpcf7_swv_create_rule('required', [
                        'field' => $tag->name,
                        'error' => wpcf7_get_message($messageKey),
                    ]),
                );
            }
        }
    }

    public function tagGeneratorVerifyEmail($contact_form, $options): void
    {
        $tgg = new \WPCF7_TagGeneratorGenerator($options['content']);
        $formatter = new \WPCF7_HTMLFormatter();

        $formatter->append_start_tag('header', ['class' => 'description-box']);
        $formatter->append_start_tag('h3');
        $formatter->append_preformatted(esc_html__('Email verification field', 'wp-sms'));
        $formatter->end_tag('h3');
        $formatter->append_start_tag('p');
        $formatter->append_preformatted(esc_html__('Adds an email field with OTP verification (powered by WSMS). The visitor enters their email, receives a verification code, and must confirm it before the form can be submitted.', 'wp-sms'));
        $formatter->end_tag('header');

        $formatter->append_start_tag('div', ['class' => 'control-box']);
        $formatter->call_user_func(static function () use ($tgg) {
            $tgg->print('field_type', [
                'with_required' => true,
                'select_options' => ['wsms_verify_email' => __('Email verification field', 'wp-sms')],
            ]);
            $tgg->print('field_name');
            $tgg->print('class_attr');
            $tgg->print('default_value', ['with_placeholder' => true]);
        });
        $formatter->end_tag('div');

        $formatter->append_start_tag('footer', ['class' => 'insert-box']);
        $formatter->call_user_func(static function () use ($tgg) {
            $tgg->print('insert_box_content');
            $tgg->print('mail_tag_tip');
        });

        $formatter->print();
    }

    public function tagGeneratorPhone($contact_form, $options): void
    {
        $tgg = new \WPCF7_TagGeneratorGenerator($options['content']);
        $formatter = new \WPCF7_HTMLFormatter();

        $formatter->append_start_tag('header', ['class' => 'description-box']);
        $formatter->append_start_tag('h3');
        $formatter->append_preformatted(esc_html__('Phone field', 'wp-sms'));
        $formatter->end_tag('h3');
        $formatter->append_start_tag('p');
        $formatter->append_preformatted(esc_html__('International phone field with country picker. Optionally require SMS verification.', 'wp-sms'));
        $formatter->end_tag('header');

        $formatter->append_start_tag('div', ['class' => 'control-box']);
        $formatter->call_user_func(static function () use ($tgg) {
            $tgg->print('field_type', [
                'with_required' => true,
                'select_options' => ['wsms_phone' => __('Phone field', 'wp-sms')],
            ]);
            $tgg->print('field_name');
        });

        $formatter->append_start_tag('fieldset');
        $formatter->append_start_tag('legend');
        $formatter->append_preformatted(esc_html__('Verification', 'wp-sms'));
        $formatter->end_tag('legend');
        $formatter->append_start_tag('label');
        $formatter->append_start_tag('input', [
            'type' => 'checkbox',
            'data-tag-part' => 'option',
            'data-tag-option' => 'verify',
        ]);
        $formatter->append_whitespace();
        $formatter->append_preformatted(esc_html__('Require SMS verification', 'wp-sms'));
        $formatter->end_tag('label');
        $formatter->end_tag('fieldset');

        $formatter->call_user_func(static function () use ($tgg) {
            $tgg->print('class_attr');
            $tgg->print('default_value', ['with_placeholder' => true]);
        });
        $formatter->end_tag('div');

        $formatter->append_start_tag('footer', ['class' => 'insert-box']);
        $formatter->call_user_func(static function () use ($tgg) {
            $tgg->print('insert_box_content');
            $tgg->print('mail_tag_tip');
        });

        $formatter->print();
    }

    public function renderEmailTag($tag): string
    {
        return $this->renderEmailVerifyTag($tag);
    }

    public function renderPhoneTag($tag): string
    {
        $name = $tag->name;
        $hasVerify = $tag->has_option('verify');

        $validationError = function_exists('wpcf7_get_validation_error')
            ? wpcf7_get_validation_error($name)
            : '';

        $containerClasses = 'wsms-phone-input wsms-phone-container';
        $classOption = $tag->get_option('class', '', true);
        if ($classOption !== '') {
            $containerClasses .= ' ' . $classOption;
        }

        $dataAttrs = sprintf(' data-wsms-field="%s"', esc_attr($name));

        if ($hasVerify) {
            $dataAttrs .= ' data-wsms-verify="1"';
        }

        if ($tag->is_required()) {
            $dataAttrs .= ' data-wsms-required="1"';
        }

        $value = (string) reset($tag->values);
        if ($tag->has_option('placeholder') || $tag->has_option('watermark')) {
            $dataAttrs .= sprintf(' data-wsms-placeholder="%s"', esc_attr($value));
        }

        $defaultOption = $tag->get_option('default', '', true);
        if ($defaultOption !== '') {
            $dataAttrs .= sprintf(' data-wsms-initial="%s"', esc_attr($defaultOption));
        }

        $idOption = $tag->get_option('id', '', true);
        if ($idOption !== '') {
            $dataAttrs .= sprintf(' data-wsms-id="%s"', esc_attr($idOption));
        }

        $wrapClass = 'wpcf7-form-control-wrap wsms-phone-wrap';
        if ($validationError) {
            $wrapClass .= ' wpcf7-not-valid';
        }

        $html = sprintf(
            '<span class="%s" data-name="%s">',
            esc_attr($wrapClass),
            esc_attr($name),
        );

        // Use <span> not <div> — CF7's wpautop closes <span> wrappers before block elements
        $html .= sprintf(
            '<span class="%s"%s></span>',
            esc_attr($containerClasses),
            $dataAttrs,
        );

        $html .= $validationError;

        if ($hasVerify) {
            $html .= sprintf(
                '<span class="wsms-verify-widget-container" style="display:block" data-wsms-channel="phone" data-wsms-field="%s"></span>',
                esc_attr($name),
            );
            $html .= sprintf(
                '<input type="hidden" name="wsms_verified_%s" class="wsms-verified-flag" value="" />',
                esc_attr($name),
            );
        }

        $html .= '</span>';

        return $html;
    }

    public function validateEmail($result, $tag)
    {
        return $this->validateEmailTag($result, $tag);
    }

    public function validatePhone($result, $tag)
    {
        $name = $tag->name;
        $phone = isset($_POST[$name]) ? sanitize_text_field(wp_unslash($_POST[$name])) : '';
        $phone = trim($phone);

        if ($tag->is_required() && $phone === '') {
            $result->invalidate($tag, $this->getMessage('wsms_phone_required'));

            return $result;
        }

        if ($phone === '') {
            return $result;
        }

        if (!preg_match('/^\+[1-9]\d{1,14}$/', $phone)) {
            $result->invalidate($tag, $this->getMessage('wsms_phone_invalid'));

            return $result;
        }

        if ($tag->has_option('verify')) {
            $sessionToken = isset($_POST['wsms_verified_' . $name])
                ? sanitize_text_field(wp_unslash($_POST['wsms_verified_' . $name]))
                : '';

            if (empty($sessionToken) || !$this->verificationService->isVerified('phone', $phone, $sessionToken)) {
                $result->invalidate($tag, $this->getMessage('wsms_verify_phone_not_verified'));
            }
        }

        return $result;
    }

    public function enqueueAssets(): void
    {
        $baseUrl = plugin_dir_url(WP_SMS_MAIN_FILE);
        $version = WP_SMS_VERSION;

        // Phone input bundle (lite-phone-input vanilla + CSS)
        wp_enqueue_style('wsms-cf7-phone', $baseUrl . 'public/js/cf7-phone.css', [], $version);
        wp_enqueue_script('wsms-cf7-phone', $baseUrl . 'public/js/cf7-phone.js', [], $version, true);

        // Phone input config (default country, preferred, restrictions)
        wp_localize_script('wsms-cf7-phone', 'wsmsCf7PhoneConfig',
            $this->restrictionSettings->getPhoneInputDisplayConfig()
        );

        // Verify widget (for both email tags and phone verify)
        $this->enqueueVerifyWidget();

        // Inline script for EMAIL verify only (phone handled by cf7-phone-entry.js)
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

    private function renderEmailVerifyTag($tag): string
    {
        $name = $tag->name;

        $validationError = function_exists('wpcf7_get_validation_error')
            ? wpcf7_get_validation_error($name)
            : '';

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
        $atts .= ' type="email"';
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
            . '<input%2$s />%3$s'
            . '<span class="wsms-verify-widget-container" style="display:block" data-wsms-channel="email" data-wsms-field="%1$s"></span>'
            . '<input type="hidden" name="wsms_verified_%1$s" class="wsms-verified-flag" value="" />'
            . '</span>',
            esc_attr($name),
            $atts,
            $validationError,
        );
    }

    private function validateEmailTag($result, $tag)
    {
        $name = $tag->name;
        $identifier = isset($_POST[$name]) ? sanitize_text_field(wp_unslash($_POST[$name])) : '';
        $sessionToken = isset($_POST['wsms_verified_' . $name]) ? sanitize_text_field(wp_unslash($_POST['wsms_verified_' . $name])) : '';

        if ($tag->is_required() && empty($identifier)) {
            $result->invalidate($tag, $this->getMessage('wsms_verify_required'));

            return $result;
        }

        if (!empty($identifier)) {
            if (empty($sessionToken) || !$this->verificationService->isVerified('email', $identifier, $sessionToken)) {
                $result->invalidate($tag, $this->getMessage('wsms_verify_email_not_verified'));
            }
        }

        return $result;
    }

    private function getMessage(string $key): string
    {
        if (function_exists('wpcf7_get_message')) {
            return wpcf7_get_message($key);
        }

        $defaults = $this->registerMessages([]);

        return $defaults[$key]['default'] ?? '';
    }
}
