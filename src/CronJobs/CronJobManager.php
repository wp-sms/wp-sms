<?php

namespace WP_SMS\CronJob;

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
