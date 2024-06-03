<?php

namespace WP_SMS\Components;

/**
 * Simple singleton that will be extended in other classes.
 */
class Singleton
{
    /**
     * @var T[]
     */
    private static $instances = [];

    /**
     * The Singleton's constructor should always be private to prevent direct construction calls with the `new` operator.
     */
    protected function __construct()
    {
    }

    /**
     * Singletons should not be cloneable.
     */
    protected function __clone()
    {
    }

    /**
     * Returns the extended instance.
     *
     * @return  static  Extended instance.
     */
    public static function getInstance()
    {
        $class = static::class;

        if (!isset(self::$instances[$class])) {
            self::$instances[$class] = new static();
        }

        return self::$instances[$class];
    }
}
