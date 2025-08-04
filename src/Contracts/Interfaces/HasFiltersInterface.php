<?php

namespace WP_SMS\Contracts\Interfaces;

interface HasFiltersInterface {
    /**
     * Register filters and actions used by the service.
     */
    public function registerHooks(): void;
}
