<?php

namespace WSms\Migration;

defined('ABSPATH') || exit;

final class DetectionResult
{
    public function __construct(
        public readonly bool $installed,
        public readonly bool $active,
        public readonly ?string $version,
        public readonly bool $dataFound,
        public readonly int $userCount,
        public readonly array $summary = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'installed'  => $this->installed,
            'active'     => $this->active,
            'version'    => $this->version,
            'data_found' => $this->dataFound,
            'user_count' => $this->userCount,
            'summary'    => $this->summary,
        ];
    }
}
