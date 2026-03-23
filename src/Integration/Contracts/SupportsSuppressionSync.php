<?php

namespace WSms\Integration\Contracts;

defined('ABSPATH') || exit;

interface SupportsSuppressionSync
{
    /**
     * Returns contacts whose deliverability status changed since last poll.
     * Each entry: {email, status: unsubscribed|bounced|complained, changed_at}
     *
     * @return array{events: array, cursor: string|null}
     */
    public function pollSuppressions(array $config, ?string $cursor = null): array;
}
