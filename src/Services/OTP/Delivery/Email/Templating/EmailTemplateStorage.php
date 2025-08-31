<?php

namespace WP_SMS\Services\OTP\Delivery\Email\Templating;

use WP_SMS\Option;

class EmailTemplateStorage
{
    private const OPT_KEY = 'wpsms_email_templates_v1';
    private const MAX_LEN = 5000;

    /**
     * @return array[]
     */
    private function map(): array
    {
        return [
            'otp_code'       => [
                'subject_key' => 'email_tpl_otp_subject',
                'body_key'    => 'email_tpl_otp_body',
                'revert_key'  => 'email_tpl_otp_revert',
            ],
            'magic_link'     => [
                'subject_key' => 'email_tpl_magic_subject',
                'body_key'    => 'email_tpl_magic_body',
                'revert_key'  => 'email_tpl_magic_revert',
            ],
            'password_reset' => [
                'subject_key' => 'email_tpl_reset_subject',
                'body_key'    => 'email_tpl_reset_body',
                'revert_key'  => 'email_tpl_reset_revert',
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
            $subject = Option::getOption($map['subject_key']);
            $body    = Option::getOption($map['body_key']);

            $revert = (bool)Option::getOption($map['revert_key']);
            if ($revert) {
                return null;
            }
            if ($subject !== '' || $body !== '') {
                return [
                    'subject' => $this->truncate($subject),
                    'body'    => $this->truncate($body),
                ];
            }
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
        if (mb_strlen($s) > self::MAX_LEN) {
            $s = mb_substr($s, 0, self::MAX_LEN);
        }
        return $s;
    }
}
