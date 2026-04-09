<?php

namespace WSms\Support;

defined('ABSPATH') || exit;

/**
 * Minimal clock abstraction so time-dependent logic is testable.
 *
 * Production code depends on the interface; tests inject a FixedClock that
 * can be advanced deterministically.
 */
interface Clock
{
    public function now(): int;
}
