<?php

namespace WP_SMS\Admin;

use WP_SMS\Admin\Pages\SettingAdminPage;
use WP_SMS\Services\CronJobs\WeeklyReport;
use WP_SMS\Admin\Logs\LogsPageProvider;
use WP_SMS\Admin\Logs\Pages\AuthenticationEventLogPage;
use WP_SMS\Admin\Reports\ReportsPageProvider;
use WP_SMS\Admin\Reports\Pages\ActivityOverviewReportPage;

class AdminManager
{
    public function init()
    {
        $this->registerAdminPages();
        $this->registerLogsPages();
        $this->registerReportsPages();
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

    public function registerLogsPages(){
        // Initialize Logs Provider
        $logsProvider = LogsPageProvider::instance();
        $logsProvider->register('auth-events', new AuthenticationEventLogPage());
    }

    public function registerReportsPages(){
        // Initialize Reports Provider
        $reportsProvider = ReportsPageProvider::instance();
        $reportsProvider->register('activity-overview', new ActivityOverviewReportPage());
    }
}
