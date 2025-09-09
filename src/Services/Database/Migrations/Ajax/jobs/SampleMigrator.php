<?php

namespace WP_SMS\Services\Database\Migrations\Ajax\Jobs;

use WP_SMS;
use WP_SMS\Services\Database\Migrations\Ajax\AbstractAjax;

class SampleMigrator extends AbstractAjax
{
    protected function getTotal($needCaching = true)
    {
        WP_SMS::log('Ajax: getTotal triggered');
    }

    protected function calculateOffset()
    {
        WP_SMS::log('Ajax: calculateOffset triggered');
    }

    protected function migrate()
    {
        WP_SMS::log('Ajax: migrate triggered');
    }
}