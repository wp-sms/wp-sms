<?php

namespace WPSmsTwoWay\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

abstract class AbstractModel extends Model
{
    /**
     * Abstract method to be overridden by model for table structure.
     *
     * @param Illuminate\Database\Schema\Blueprint $table
     * @return void
     */
    abstract public function createTable(Blueprint $table);

    /**
     * Install the associated table with the model
     *
     * @return void
     */
    public static function installTable()
    {
        $model = new static;
        if (! Capsule::Schema()->hasTable($model->table)) {
            Capsule::Schema()->create($model->table, function ($table) use ($model) {
                $model->createTable($table);
            });
        }
    }

    /**
     * Drop the associated table with the model
     *
     * @return void
     */
    public static function dropTable()
    {
        $model = new static;
        Capsule::Schema()->dropIfExists($model->table);
    }
}
