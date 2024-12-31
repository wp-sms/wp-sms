<?php

namespace WP_SMS\Widget;

abstract class AbstractWidget
{
    /**
     * Default capabilities for accessing the widget.
     * Subclasses can override this property.
     *
     * @var array
     */
    protected $capabilities = 'manage_options';

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

        // Validate $capabilities
        if (!is_array($this->capabilities)) {
            throw new \Exception('The $capabilities property must be an array.');
        }
    }

    /**
     * Check if the current user has at least one of the required capabilities.
     *
     * @return bool
     */
    protected function userHasCapabilities()
    {
        foreach ($this->capabilities as $capability) {
            if (current_user_can($capability)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Register the widget
     *
     * @return void
     */
    public function register()
    {
        add_action('wp_dashboard_setup', function () {
            if ($this->userHasCapabilities()) {
                wp_add_dashboard_widget($this->id, $this->name, function (...$args) {
                    if (method_exists($this, 'prepare')) {
                        call_user_func([$this, 'prepare']);
                    }
                    call_user_func([$this, 'render'], ...$args);
                });
            }
        });
    }
}
