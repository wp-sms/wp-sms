<?php

namespace WSms\Exception;

defined('ABSPATH') || exit;

/**
 * Thrown when an operation conflicts with existing state (e.g. duplicate resource).
 */
class ConflictException extends DomainException
{
    public static function duplicate(string $type, string $identifier): self
    {
        return new self(sprintf('%s already exists: %s', $type, $identifier));
    }
}
