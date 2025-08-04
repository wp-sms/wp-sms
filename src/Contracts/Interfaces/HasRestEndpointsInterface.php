<?php

namespace WP_SMS\Contracts\Interfaces;

interface HasRestEndpointsInterface {
    /**
     * Register REST API endpoints for the service.
     * Called during rest_api_init.
     */
    public function registerRestRoutes(): void;
}
