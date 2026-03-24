<?php

namespace WSms\Exception;

defined('ABSPATH') || exit;

/**
 * Thrown when a service is not registered in the container.
 */
class ServiceNotFoundException extends \RuntimeException implements ExceptionInterface
{
}
