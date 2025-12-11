<?php

namespace WP_SMS\Abstracts;

if (!defined('ABSPATH')) exit;

abstract class BaseView
{
    protected $dataProvider;

    abstract protected function render();
}