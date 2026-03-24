<?php

namespace WSms\Exception;

defined('ABSPATH') || exit;

/**
 * Thrown when a requested entity does not exist.
 */
class NotFoundException extends DomainException
{
    public static function entity(string $type, string $id): self
    {
        return new self(sprintf('%s with ID %s not found.', $type, $id));
    }
}
