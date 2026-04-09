<?php

namespace WSms\Support;

defined('ABSPATH') || exit;

/**
 * Default production clock — wraps the native time() function.
 */
class SystemClock implements Clock
{
    public function now(): int
    {
        return time();
    }
}
