<?php

namespace WP_SMS\Abstracts;


abstract class BaseView
{
    protected $dataProvider;

    abstract protected function render();    
}