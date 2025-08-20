<?php

namespace WP_SMS\Services\Email;

use WP_SMS\User\RegisterUserViaPhone;
use WP_SMS\User\UserHelper;

class EmailManager
{
    public function init()
    {
        add_filter('pre_wp_mail', [$this, 'preFilterPhoneRegisteredEmails'], 10, 2);
    }

    /**
     * pre_wp_mail short-circuit filter.
     *
     * @param null|bool|\WP_Error $pre Null to continue default sending, or a bool/WP_Error to short-circuit.
     * @param array $atts Full mail atts: ['to','subject','message','headers','attachments'].
     * @return null|bool|\WP_Error
     */
    public function preFilterPhoneRegisteredEmails($pre, $atts)
    {
        if (empty($atts['to'])) {
            return $pre;
        }

        $originalTo = $atts['to'];
        $recipients = is_array($originalTo) ? $originalTo : explode(',', $originalTo);
        $filtered   = [];

        foreach ($recipients as $email) {
            $email = trim($email);

            if (!is_email($email)) {
                $filtered[] = $email;
                continue;
            }

            $user = get_user_by('email', $email);

            if (!$user) {
                $filtered[] = $email;
                continue;
            }

            $isPhoneUser = RegisterUserViaPhone::isPhoneRegistered($user->ID) ||
                $this->isGeneratedEmail($user->user_email);

            if (!$isPhoneUser) {
                $filtered[] = $email;
            }
        }

        if (empty($filtered)) {
            return true;
        }

        $normalizedOriginal = array_map('trim', is_array($originalTo) ? $originalTo : explode(',', $originalTo));
        $normalizedFiltered = array_map('trim', $filtered);

        $o = $normalizedOriginal;
        $f = $normalizedFiltered;
        sort($o);
        sort($f);
        if ($o === $f) {
            return $pre;
        }

        remove_filter('pre_wp_mail', [$this, 'preFilterPhoneRegisteredEmails'], 10);
        try {
            $result = wp_mail(
                is_array($originalTo) ? $filtered : implode(',', $filtered),
                $atts['subject'] ?? '',
                $atts['message'] ?? '',
                $atts['headers'] ?? '',
                $atts['attachments'] ?? []
            );
        } finally {
            add_filter('pre_wp_mail', [$this, 'preFilterPhoneRegisteredEmails'], 10, 2);
        }

        return $result;
    }

    private function isGeneratedEmail($email): bool
    {
        $localPart = strtok($email, '@');
        return strpos($localPart, UserHelper::GENERATED_EMAIL_PREFIX) === 0;
    }
}