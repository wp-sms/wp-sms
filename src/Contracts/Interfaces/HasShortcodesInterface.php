<?php

namespace WP_SMS\Contracts\Interfaces;

interface HasShortcodesInterface {
    /**
     * Register all WordPress shortcodes for the service.
     */
    public function registerShortcodes(): void;
}
