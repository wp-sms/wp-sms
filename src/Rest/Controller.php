<?php

namespace WSms\Rest;

defined('ABSPATH') || exit;

abstract class Controller
{
    protected const NAMESPACE = 'wsms/v1';

    abstract public function registerRoutes(): void;

    public function canManage(): bool
    {
        return current_user_can('manage_options');
    }
}
