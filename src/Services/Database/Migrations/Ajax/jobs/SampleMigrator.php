<?php

namespace WP_SMS\Services\Database\Migrations\Ajax\Jobs;

use WP_SMS;
use WP_SMS\Services\Database\Migrations\Ajax\AbstractAjax;

class SampleMigrator extends AbstractAjax
{
    protected function getTotal($needCaching = true)
    {
        // Silence is golden
    }

    protected function calculateOffset()
    {
        // Silence is golden
    }

    protected function migrate()
    {
        WP_SMS::log('Ajax: migrate triggered.');
    }
}