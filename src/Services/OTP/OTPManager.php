<?php

namespace WP_SMS\Services\OTP;

use WP_SMS\Contracts\Abstracts\AbstractService;
use WP_SMS\Services\OTP\Admin\Pages\OTPAdminPage;
use WP_SMS\Services\OTP\Shortcodes\AuthShortcodes;
use WP_SMS\Services\OTP\Templates\AuthTemplates;
use WP_SMS\Services\OTP\Assets\AuthAssets;
use WP_SMS\Services\OTP\RestAPIEndpoints\Register\RegisterInitApiEndpoints;
use WP_SMS\Services\OTP\RestAPIEndpoints\Register\RegisterStartAPIEndpoint;
use WP_SMS\Services\OTP\RestAPIEndpoints\Register\RegisterVerifyAPIEndpoint;
use WP_SMS\Services\OTP\RestAPIEndpoints\Register\RegisterAddIdentifierAPIEndpoint;

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
            new RegisterInitApiEndpoints(),
            new RegisterStartAPIEndpoint(),
            new RegisterVerifyAPIEndpoint(),
            new RegisterAddIdentifierAPIEndpoint(),
        ];

        foreach ($services as $service) {
            $service->init();
        }
    }
}
