<?php

namespace WP_SMS\Shortcode;

use Exception;

class ShortcodeAbstract
{

    /**
     * @return void
     */
    public static function boot()
    {
        try {

            $class  = self::getClassName();
            $action = new $class;

            $action->init();

        } catch (Exception $e) {
            error_log($e->getMessage());
        }
    }

    /**
     * @return void
     */
    public function init()
    {
    }

    /**
     * @return string
     */
    public static function getClassName()
    {
        return get_called_class();
    }

    public function retrieveData()
    {

    }

}