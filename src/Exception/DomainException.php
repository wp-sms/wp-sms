<?php

namespace WSms\Exception;

defined('ABSPATH') || exit;

/**
 * Base exception for all domain-level errors.
 */
class DomainException extends \RuntimeException implements ExceptionInterface
{
}
