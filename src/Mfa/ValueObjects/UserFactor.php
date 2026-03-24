<?php

namespace WSms\Mfa\ValueObjects;

use WSms\Enums\ChannelStatus;

class UserFactor
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly string $channelId,
        public readonly ChannelStatus $status,
        public readonly array $meta,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {
    }

    /**
     * Create from a database row (associative array).
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            userId: (int) $row['user_id'],
            channelId: $row['channel_id'],
            status: ChannelStatus::from($row['status']),
            meta: json_decode($row['meta'] ?: '{}', true) ?? [],
            createdAt: $row['created_at'],
            updatedAt: $row['updated_at'],
        );
    }
}
