<?php

namespace WSms\Messaging\Contracts;

defined('ABSPATH') || exit;

/**
 * Optional capability for gateways whose webhook receivers must reply with a
 * non-JSON body (e.g. the literal string "OK" with text/plain Content-Type).
 *
 * The default callback flow returns a JSON `{success: true}` response; some
 * providers (SMSAPI is one) treat any other body as a delivery failure and
 * keep retrying. Implementing this lets the gateway controller short-circuit
 * the JSON serialization and emit the provider's expected acknowledgement.
 */
interface SupportsCustomCallbackResponse
{
    /**
     * Body to return for a callback acknowledgement, or null to fall back to the default JSON.
     *
     * @param string $type One of 'status' or 'inbound'.
     */
    public function getCallbackResponseBody(string $type, \WP_REST_Request $request): ?string;
}
