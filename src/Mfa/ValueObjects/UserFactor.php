<?php

namespace WSms\Mfa\ValueObjects;

use WSms\Enums\ChannelStatus;

readonly class UserFactor
{
    public function __construct(
        public int $id,
        public int $userId,
        public string $channelId,
        public ChannelStatus $status,
        public array $meta,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    /**
     * Create from a database row object.
     */
    public static function fromRow(object $row): self
    {
        return new self(
            id: (int) $row->id,
            userId: (int) $row->user_id,
            channelId: $row->channel_id,
            status: ChannelStatus::from($row->status),
            meta: json_decode($row->meta ?: '{}', true) ?? [],
            createdAt: $row->created_at,
            updatedAt: $row->updated_at,
        );
    }
}
