<?php

namespace WSms\Campaign;

defined('ABSPATH') || exit;

class AudienceBatch
{
    /**
     * @param array<array{contact_id: ?string, recipient: string, first_name: ?string, last_name: ?string, custom_fields: array}> $recipients
     */
    public function __construct(
        public readonly array $recipients,
        public readonly bool $hasMore,
        public readonly int $totalCount,
        public readonly ?string $lastId,
        public readonly int $skippedCount = 0,
    ) {
    }
}
