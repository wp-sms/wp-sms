<?php

namespace WP_SMS\Widget;

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
     * Default capability for accessing the widget.
     * Subclasses can override this property.
     *
     * @var string
     */
    protected $capability = 'manage_options';

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

        // Validate $capability
        if (!is_string($this->capability)) {
            $this->capability = 'manage_options'; // Default fallback capability
        }
    }

    /**
     * Check if the current user has the required capability.
     *
     * @return bool
     */
    protected function userHasCapabilities()
    {
        return current_user_can($this->capability);
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
