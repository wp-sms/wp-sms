<?php

namespace WP_SMS\Controller;

class ControllerManager
{
    public function init()
    {
        $this->registerPublicControllers();
        $this->registerAdminControllers();
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
    }
}