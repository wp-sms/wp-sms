<?php

namespace WSms\Exception;

defined('ABSPATH') || exit;

/**
 * Thrown when a database operation fails (wraps $wpdb errors).
 */
class PersistenceException extends DomainException
{
}
