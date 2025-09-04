<?php

namespace WP_SMS\Services\OTP\Delivery\PhoneNumber\Templating;

use WP_SMS\Utils\Sanitizer;

class TemplateRenderer
{
    /**
     * Render an SMS template.
     *
     * @param string $templateId
     * @param array $context
     * @return array ['body' => string]
     */
    public function render(string $templateId, array $context): array
    {
        $def = SmsTemplateRegistry::get($templateId);
        if (!$def) {
            $def = SmsTemplateRegistry::get(SmsTemplate::TYPE_OTP_CODE);
        }

        $storage = new SmsTemplateStorage();
        $custom  = $storage->getCustom($def->id);

        $bodyTmpl = $custom['body'] ?? $def->defaultBody;

        $bodyTmpl = apply_filters('wpsms_sms_template_body', $bodyTmpl, $def->id, $context);

        $bodyTmpl = Sanitizer::sanitizeBody($bodyTmpl);

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
                    return esc_url_raw((string)$val);
                }
                return wp_strip_all_tags((string)$val);
            }
            return '';
        };

        $body = preg_replace_callback('/\{\{[a-zA-Z0-9_\-]+\}\}/', $replacer, $bodyTmpl);

        $body = trim(preg_replace("/[ \t]+/", ' ', preg_replace("/\r\n|\r|\n/", ' ', $body)));

        return compact('body');
    }
}
