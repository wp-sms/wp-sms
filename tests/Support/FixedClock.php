<?php

namespace WSms\Tests\Support;

use WSms\Support\Clock;

/**
 * Mutable Clock for tests. Time does not advance unless you tell it to.
 */
class FixedClock implements Clock
{
    public function __construct(private int $now = 1_700_000_000)
    {
    }

    public function now(): int
    {
        return $this->now;
    }

    public function set(int $now): void
    {
        $this->now = $now;
    }

    public function advance(int $seconds): void
    {
        $this->now += $seconds;
    }
}
