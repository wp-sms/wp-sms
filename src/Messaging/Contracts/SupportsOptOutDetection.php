<?php

namespace WSms\Messaging\Contracts;

defined('ABSPATH') || exit;

interface SupportsOptOutDetection
{
    /**
     * Check if a delivery failure indicates the recipient has opted out.
     */
    public function isOptOutError(DeliveryResult $result): bool;
}
