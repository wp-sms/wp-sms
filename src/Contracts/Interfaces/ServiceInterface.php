<?php

namespace WP_SMS\Contracts\Interfaces;

interface ServiceInterface {
    /**
     * Unique slug to identify the service (e.g., 'otp', 'notifications').
     */
    public function getSlug(): string;

    /**
     * Called in the plugin's bootstrap flow (on plugins_loaded).
     * This method must be implemented in every service.
     */
    public function init(): void;
}
