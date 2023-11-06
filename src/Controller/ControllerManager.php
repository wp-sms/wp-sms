<?php

namespace WP_SMS\Controller;

class ControllerManager
{
    public function init()
    {
        $this->registerControllers();
    }

    public function registerControllers()
    {
        SubscriberFormAjax::listen();
        GroupFormAjax::listen();
        PrivacyDataAjax::listen();
        ExportAjax::listen();
        UploadSubscriberCsv::listen();
        ImportSubscriberCsv::listen();
    }
}