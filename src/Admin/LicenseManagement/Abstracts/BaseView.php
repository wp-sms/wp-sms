<?php

namespace WP_SMS\Admin\LicenseManagement\Abstracts;

if (!defined('ABSPATH')) exit;

abstract class BaseView
{
    protected $dataProvider;

    abstract protected function render();
}