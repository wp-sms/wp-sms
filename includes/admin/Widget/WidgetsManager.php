<?php

namespace WP_SMS\Admin\Widget;

use WP_SMS\Helper;

class WidgetsManager
{
    /**
     * @var array
     */
    private $widgets = [
        'StatsWidget' => Widgets\StatsWidget::class,
    ];

    /**
     * Init widgets
     *
     * @return void
     */
    public static function init()
    {
        $instance = new self;
        $instance->includeRequirements();
        $instance->loadWidgets();
        $instance->registerAssets();
    }

    /**
     * Include requirements
     *
     * @return void
     */
    private function includeRequirements()
    {
        require_once WP_SMS_DIR.'includes/admin/Widget/AbstractWidget.php';
    }

    /**
     * Require files in widgets folder
     *
     * @return void
     */
    private function loadWidgets()
    {
        foreach ($this->widgets as $fileName => $widget) {
            $file = WP_SMS_DIR."includes/admin/Widget/Widgets/{$fileName}.php";
            if (file_exists($file)) {
                require_once $file;
            }
            if (is_subclass_of($widget, AbstractWidget::class)) {
                (new $widget)->register();
            }
        }
    }

    /**
     * Register widgets common assets
     *
     * @return void
     */
    private function registerAssets()
    {
    }
}

widgetsManager::init();
