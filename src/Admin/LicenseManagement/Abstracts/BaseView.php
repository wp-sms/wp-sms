<?php

namespace WP_SMS\Admin\LicenseManagement\Abstracts;


abstract class BaseView
{
    protected $dataProvider;

    abstract protected function render();    
}