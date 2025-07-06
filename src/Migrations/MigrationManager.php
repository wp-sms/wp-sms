<?php

namespace WP_SMS\Migrations;


class MigrationManager
{

    public function init()
    {
        $this->registerMigrations();
    }

    public function registerMigrations()
    {
        $weeklyReport = new OptionMigrations();
        $weeklyReport->register();
    }
}