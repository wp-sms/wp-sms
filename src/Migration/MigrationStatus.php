<?php

namespace WSms\Migration;

defined('ABSPATH') || exit;

enum MigrationStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Paused = 'paused';
    case Completed = 'completed';
    case Failed = 'failed';
    case RolledBack = 'rolled_back';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Failed, self::RolledBack => true,
            default => false,
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Pending => in_array($target, [self::Running], true),
            self::Running => in_array($target, [self::Paused, self::Completed, self::Failed], true),
            self::Paused => in_array($target, [self::Running, self::RolledBack], true),
            self::Completed => in_array($target, [self::RolledBack], true),
            self::Failed => in_array($target, [self::Running, self::RolledBack], true),
            self::RolledBack => false,
        };
    }
}
