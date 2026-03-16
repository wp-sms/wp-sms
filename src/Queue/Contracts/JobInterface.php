<?php

namespace WSms\Queue\Contracts;

defined('ABSPATH') || exit;

interface JobInterface
{
    public function getType(): string;

    public function getPayload(): array;

    public function getMaxRetries(): int;

    public function getRetryBackoff(): int;
}
