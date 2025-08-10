<?php

namespace WP_SMS\Services\Email;

use WP_SMS\User\RegisterUserViaPhone;
use WP_SMS\User\UserHelper;

class EmailManager
{
    public function init()
    {
        add_filter('wp_mail', [$this, 'filterPhoneRegisteredEmails']);
    }

    public function filterPhoneRegisteredEmails($args)
    {
        if (empty($args['to'])) {
            return $args;
        }

        $recipients = is_array($args['to']) ? $args['to'] : explode(',', $args['to']);
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

        $args['to'] = is_array($args['to']) ? $filtered : implode(',', $filtered);
        return $args;
    }

    private function isGeneratedEmail($email): bool
    {
        $localPart = strtok($email, '@');
        return strpos($localPart, UserHelper::GENERATED_EMAIL_PREFIX) === 0;
    }
}