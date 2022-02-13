<?php

namespace WPSmsTwoWay\Container;

use League\Container\ServiceProvider\AbstractServiceProvider;

/**
 * Class ServiceProvider
 * @package WPSmsTwoWay\Container
 */
abstract class ServiceProvider extends AbstractServiceProvider
{
    /**
     * @var $autoload
     */
    protected $autoload;

    /**
     * ServiceProvider constructor.
     */
    public function __construct()
    {
        if (is_array($this->autoload)) {
            foreach ($this->autoload as $class) {
                if (class_exists($class)) {
                    new $class;
                }
            }
        }
    }
}
