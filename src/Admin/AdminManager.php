<?php

namespace WP_SMS\Admin;

use WP_SMS\Admin\Pages\SettingAdminPage;
use WP_SMS\Services\CronJobs\WeeklyReport;

class AdminManager
{
    public function init()
    {
        $this->registerAdminPages();
    }

    public function registerAdminPages()
    {
        $adminPages = [
            new SettingAdminPage()
        ];

        foreach ($adminPages as $adminPage) {
            if (method_exists($adminPage, 'register')) {
                $adminPage->register();
            }
        }
    }
}
