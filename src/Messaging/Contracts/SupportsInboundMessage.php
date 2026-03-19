<?php

namespace WSms\Messaging\Contracts;

defined('ABSPATH') || exit;

interface SupportsInboundMessage
{
    public function validateInboundCallback(\WP_REST_Request $request): bool;

    /** @return InboundMessage[] */
    public function parseInboundCallback(\WP_REST_Request $request): array;

    public function getInboundCallbackUrl(): string;
}
