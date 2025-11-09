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
use WP_SMS\Services\OTP\RestAPIEndpoints\Login\LoginInitApiEndpoints;
use WP_SMS\Services\OTP\RestAPIEndpoints\Login\LoginStartAPIEndpoint;
use WP_SMS\Services\OTP\RestAPIEndpoints\Login\LoginVerifyAPIEndpoint;
use WP_SMS\Services\OTP\RestAPIEndpoints\Login\LoginMfaChallengeAPIEndpoint;
use WP_SMS\Services\OTP\RestAPIEndpoints\Login\LoginMfaVerifyAPIEndpoint;
use WP_SMS\Services\OTP\RestAPIEndpoints\Account\AccountMeAPIEndpoint;
use WP_SMS\Services\OTP\RestAPIEndpoints\Account\AccountEmailAPIEndpoint;
use WP_SMS\Services\OTP\RestAPIEndpoints\Account\AccountPhoneAPIEndpoint;
use WP_SMS\Services\OTP\RestAPIEndpoints\MFA\MfaFactorsAPIEndpoint;
use WP_SMS\Services\OTP\RestAPIEndpoints\MFA\MfaEmailAPIEndpoint;
use WP_SMS\Services\OTP\RestAPIEndpoints\MFA\MfaPhoneAPIEndpoint;
use WP_SMS\Services\OTP\RestAPIEndpoints\PasswordReset\PasswordResetInitAPIEndpoint;
use WP_SMS\Services\OTP\RestAPIEndpoints\PasswordReset\PasswordResetVerifyAPIEndpoint;
use WP_SMS\Services\OTP\RestAPIEndpoints\PasswordReset\PasswordResetCompleteAPIEndpoint;
use WP_SMS\Services\OTP\Shortcodes\AccountShortcodes;

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
            new AccountShortcodes(),
            new AuthTemplates(),
            
            // Register API Endpoints
            new RegisterInitApiEndpoints(),
            new RegisterStartAPIEndpoint(),
            new RegisterVerifyAPIEndpoint(),
            new RegisterAddIdentifierAPIEndpoint(),
            
            // Login API Endpoints
            new LoginInitApiEndpoints(),
            new LoginStartAPIEndpoint(),
            new LoginVerifyAPIEndpoint(),
            new LoginMfaChallengeAPIEndpoint(),
            new LoginMfaVerifyAPIEndpoint(),
            
            // Account API Endpoints
            new AccountMeAPIEndpoint(),
            new AccountEmailAPIEndpoint(),
            new AccountPhoneAPIEndpoint(),
            
            // MFA API Endpoints
            new MfaFactorsAPIEndpoint(),
            new MfaEmailAPIEndpoint(),
            new MfaPhoneAPIEndpoint(),
            
            // Password Reset API Endpoints
            new PasswordResetInitAPIEndpoint(),
            new PasswordResetVerifyAPIEndpoint(),
            new PasswordResetCompleteAPIEndpoint(),
        ];

        foreach ($services as $service) {
            $service->init();
        }
    }
}
