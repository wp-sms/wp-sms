<?php

namespace WP_SMS\Services\OTP;

use WP_SMS\Contracts\Abstracts\AbstractService;
use WP_SMS\Services\OTP\Admin\Pages\OTPAdminPage;
use WP_SMS\Services\OTP\Shortcodes\AuthShortcodes;
use WP_SMS\Services\OTP\Templates\AuthTemplates;
use WP_SMS\Services\OTP\RestAPIEndpoints\Auth\AuthRestAPIEndpoints;
use WP_SMS\Services\OTP\Assets\AuthAssets;
use  WP_SMS\Services\OTP\RestAPIEndpoints\OTP\OTPRestAPIEndpoints;

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
        $services = [
            // Admin Pages
            new OTPAdminPage(),
            
            // Authentication Components
            new AuthAssets(),
            new AuthShortcodes(),
            new AuthTemplates(),
            new AuthRestAPIEndpoints(),
            new OTPRestAPIEndpoints(),
        ];

        foreach ($services as $service) {
            $service->init();
        }
    }
}
