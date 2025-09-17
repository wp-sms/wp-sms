<?php

namespace WP_SMS\Services\OTP;

use WP_SMS\Contracts\Abstracts\AbstractService;
use WP_SMS\Services\OTP\Admin\Pages\OTPAdminPage;
use WP_SMS\Services\OTP\Shortcodes\AuthShortcodes;
use WP_SMS\Services\OTP\Templates\AuthTemplates;
use WP_SMS\Services\OTP\Assets\AuthAssets;
use WP_SMS\Services\OTP\RestAPIEndpoints\Register\RegisterApiEndpoints;
use WP_SMS\Services\OTP\RestAPIEndpoints\Register\RegisterStartAPIEndpoint;

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
            new RegisterApiEndpoints(),
            new RegisterStartAPIEndpoint(),
        ];

        foreach ($services as $service) {
            $service->init();
        }
    }
}
