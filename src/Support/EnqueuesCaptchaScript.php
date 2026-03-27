<?php

namespace WSms\Support;

use WSms\Auth\CaptchaGuard;

defined('ABSPATH') || exit;

trait EnqueuesCaptchaScript
{
    protected function enqueueCaptchaScript(CaptchaGuard $guard): void
    {
        $url = $guard->getScriptUrl();

        if ($url) {
            wp_enqueue_script('wsms-captcha-provider', $url, [], null, true);
        }
    }
}
