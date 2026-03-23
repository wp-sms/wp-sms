<?php

namespace WSms\Integration\Contracts;

defined('ABSPATH') || exit;

interface SupportsEngagementSync
{
    /**
     * Poll for engagement data (opens, clicks) since last cursor.
     *
     * @return array{data: array, cursor: string|null}
     */
    public function pollEngagement(array $config, ?string $cursor = null): array;
}
