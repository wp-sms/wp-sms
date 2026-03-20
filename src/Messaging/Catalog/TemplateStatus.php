<?php

namespace WSms\Messaging\Catalog;

defined('ABSPATH') || exit;

enum TemplateStatus: string
{
    case Approved = 'approved';
    case Pending = 'pending';
    case Rejected = 'rejected';
    case Paused = 'paused';
    case Disabled = 'disabled';

    public static function fromProviderStatus(string $status): self
    {
        return match (strtolower($status)) {
            'approved', 'active' => self::Approved,
            'pending', 'submitted', 'in_review' => self::Pending,
            'rejected', 'denied' => self::Rejected,
            'paused', 'suspended' => self::Paused,
            default => self::Disabled,
        };
    }

    public function isUsable(): bool
    {
        return $this === self::Approved;
    }
}
