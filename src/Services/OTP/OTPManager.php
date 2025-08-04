<?php

namespace WP_SMS\Services\OTP;

use WP_SMS\Contracts\Abstracts\AbstractService;

class OTPManager extends AbstractService
{

    public function __construct()
    {
    }

    public function getSlug(): string
    {
        return 'otp';
    }

    protected function boot(): void
    {
    }
}
