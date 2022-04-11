<?php

namespace WP_SMS\Admin\Widget;

abstract class AbstractWidget
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $version;

    /**
     * Render callback
     *
     * @return string html
     */
    abstract public function render();

    public function __construct()
    {
        if (method_exists($this, 'onInit')) {
            $this->onInit();
        }
    }

    /**
     * Register the widget
     *
     * @return void
     */
    public function register()
    {
        add_action('wp_dashboard_setup', function () {
            wp_add_dashboard_widget($this->id, $this->name, function (...$args) {
                if (method_exists($this, 'prepare')) {
                    call_user_func([$this, 'prepare']);
                }
                call_user_func([$this, 'render'], ...$args);
            });
        });
    }
}
