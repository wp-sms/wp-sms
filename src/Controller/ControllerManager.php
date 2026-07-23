<?php

namespace WP_SMS\Controller;

if (!defined('ABSPATH')) exit;

class ControllerManager
{
    public function init()
    {
        $this->registerPublicControllers();
        $this->registerAdminControllers();

        // Bind the privacy-export cleanup so the scheduled deletion can run
        // (WP-Cron requests do not go through the admin bootstrap).
        add_action(PrivacyDataAjax::CLEANUP_HOOK, [PrivacyDataAjax::class, 'cleanupExport']);
    }

    /**
     * Register public controllers
     *
     * @return void
     */
    private function registerPublicControllers()
    {
        PublicSubscribeAjax::listen();
        PublicUnsubscribeAjax::listen();
        PublicVerifySubscribeAjax::listen();
    }

    /**
     * Register admin controllers
     *
     * @return void
     */
    private function registerAdminControllers()
    {
        SubscriberFormAjax::listen(false);
        GroupFormAjax::listen(false);
        PrivacyDataAjax::listen(false);
        ExportAjax::listen(false);
        UploadSubscriberCsv::listen(false);
        ImportSubscriberCsv::listen(false);
        OnBoardingTestGateway::listen(false);
        LicenseManagerAjax::listen(false);
        RecipientCountsAjax::listen(false);
        UserRolesMobileCountAjax::listen(false);
        NumberMigrationAjax::listen(false);
    }
}