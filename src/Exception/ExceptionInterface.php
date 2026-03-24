<?php

namespace WSms\Exception;

defined('ABSPATH') || exit;

/**
 * Marker interface for all WP-SMS exceptions.
 *
 * Allows catching any plugin exception with `catch (ExceptionInterface $e)`.
 */
interface ExceptionInterface extends \Throwable
{
}
