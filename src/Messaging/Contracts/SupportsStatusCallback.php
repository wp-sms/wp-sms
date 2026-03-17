<?php

namespace WSms\Messaging\Contracts;

defined('ABSPATH') || exit;

interface SupportsStatusCallback
{
    public function validateStatusCallback(\WP_REST_Request $request): bool;

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array;

    public function getStatusCallbackUrl(): string;
}
