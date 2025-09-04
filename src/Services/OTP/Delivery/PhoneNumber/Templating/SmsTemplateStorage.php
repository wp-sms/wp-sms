<?php

namespace WP_SMS\Services\OTP\Delivery\PhoneNumber\Templating;

use WP_SMS\Option;

class SmsTemplateStorage
{
    private const OPT_KEY = 'wpsms_sms_templates_v1';

    private const MAX_LEN = 2000;

    /**
     * @return array[]
     */
    private function map(): array
    {
        return [
            'otp_code'       => [
                'body_key'   => 'sms_tpl_otp_body',
                'revert_key' => 'sms_tpl_otp_revert',
            ],
            'magic_link'     => [
                'body_key'   => 'sms_tpl_magic_body',
                'revert_key' => 'sms_tpl_magic_revert',
            ],
            'password_reset' => [
                'body_key'   => 'sms_tpl_reset_body',
                'revert_key' => 'sms_tpl_reset_revert',
            ],
        ];
    }

    /**
     * @return array
     */
    public function load(): array
    {
        $saved = get_option(self::OPT_KEY, []);
        return is_array($saved) ? $saved : [];
    }

    /**
     * @param string $id
     * @return string[]|null
     */
    public function getCustom(string $id): ?array
    {
        $map = $this->map()[$id] ?? null;
        if ($map) {
            $body   = Option::getOption($map['body_key']);
            $revert = (bool)Option::getOption($map['revert_key']);

            if ($revert) {
                return null;
            }

            $defaults = SmsTemplateRegistry::get($id);
            return [
                'body' => $body !== '' ? $this->truncate($body) : $defaults->defaultBody,
            ];
        }

        $all = $this->load();
        return $all[$id] ?? null;
    }

    /**
     * @param string $s
     * @return string
     */
    private function truncate(string $s): string
    {
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($s) > self::MAX_LEN ? mb_substr($s, 0, self::MAX_LEN) : $s;
        }
        return strlen($s) > self::MAX_LEN ? substr($s, 0, self::MAX_LEN) : $s;
    }
}
