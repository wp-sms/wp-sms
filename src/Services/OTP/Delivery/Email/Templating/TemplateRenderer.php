<?php

namespace WP_SMS\Services\OTP\Delivery\Email\Templating;

use WP_SMS\Utils\Sanitizer;

class TemplateRenderer
{
    /**
     * @param string $templateId
     * @param array $context
     * @return array
     */
    public function render(string $templateId, array $context): array
    {
        $def = EmailTemplateRegistry::get($templateId);
        if (!$def) {
            $def = EmailTemplateRegistry::get(EmailTemplate::TYPE_OTP_CODE);
        }

        $storage = new EmailTemplateStorage();
        $custom  = $storage->getCustom($def->id);

        $subjectTmpl = $custom['subject'] ?? $def->defaultSubject;
        $bodyTmpl    = $custom['body'] ?? $def->defaultBody;

        $subjectTmpl = apply_filters('wpsms_email_template_subject', $subjectTmpl, $def->id, $context);
        $bodyTmpl    = apply_filters('wpsms_email_template_body', $bodyTmpl, $def->id, $context);

        $subjectTmpl = Sanitizer::sanitizeSubject($subjectTmpl);
        $bodyTmpl    = Sanitizer::sanitizeBody($bodyTmpl);


        $allowed = array_fill_keys($def->placeholders, true);

        $replacer = function (array $matches) use ($context, $allowed) {
            $raw = $matches[0];
            if (!isset($allowed[$raw])) {
                return $raw;
            }
            $key = trim($raw, '{}');
            $val = $context[$key] ?? '';

            if (is_string($val) || is_numeric($val)) {
                if (substr($key, -5) === '_link') {
                    return esc_url((string)$val);
                }
                return esc_html((string)$val);
            }
            return '';
        };

        $subject = preg_replace_callback('/\{\{[a-zA-Z0-9_\-]+\}\}/', $replacer, $subjectTmpl);
        $body    = preg_replace_callback('/\{\{[a-zA-Z0-9_\-]+\}\}/', $replacer, $bodyTmpl);

        $is_html = $body !== wp_strip_all_tags($body);

        return compact('subject', 'body', 'is_html');
    }
}
