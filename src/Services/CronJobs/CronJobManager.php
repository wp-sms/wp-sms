<?php

namespace WP_SMS\Services\CronJobs;

class CronJobManager
{
    public function init()
    {
        $this->registerCronJobs();
    }

    public function registerCronJobs()
    {
        $weeklyReport = new WeeklyReport();
        $weeklyReport->register();
    }
}
