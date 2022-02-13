<?php

namespace WPSmsTwoWay\Services;

class Migration
{
    /**
     * Models with integrated migration functionality
     *
     * @var array
     */
    private static $models =[
        \WPSmsTwoWay\Models\IncomingMessage::class,
        \WPSmsTwoWay\Models\Command::class,
    ];

    /**
     * Migrate all tables
     *
     * @return void
     */
    public static function migrate()
    {
        foreach (self::$models as $model) {
            if (is_subclass_of($model, 'WPSmsTwoWay\Models\AbstractModel')) {
                $model::installTable();
            }
        }
    }

    /**
     * Delete all tables
     *
     * @return void
     */
    public static function wipe()
    {
        foreach (self::$models as $model) {
            if (is_subclass_of($model, 'WPSmsTwoWay\Models\AbstractModel')) {
                $model::dropTable();
            }
        }
    }
    
    /**
     * Reinstall all tables
     *
     * @return void
     */
    public static function refresh()
    {
        self::wipe();
        self::migrate();
    }
}
