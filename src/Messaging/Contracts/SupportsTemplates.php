<?php

namespace WSms\Messaging\Contracts;

use WSms\Messaging\Catalog\TemplateMapping;
use WSms\Messaging\Catalog\VariableStyle;

defined('ABSPATH') || exit;

/**
 * Base interface for any gateway that can send messages via provider template ID + variables.
 *
 * Gateways implementing this support template-based sending, whether templates are
 * fetched from an API (SupportsTemplateFetch) or entered manually by the admin.
 */
interface SupportsTemplates
{
    /**
     * Whether this channel requires a pre-approved provider template for sending.
     *
     * When true, messages without a template mapping will fail rather than
     * falling back to body text (since the provider would reject them anyway).
     */
    public function requiresTemplateForChannel(string $channel): bool;

    /**
     * The variable format this provider uses (positional vs named).
     */
    public function getVariableStyle(): VariableStyle;

    /**
     * Build the provider-specific API payload for a template-based message.
     *
     * @return array Provider-specific parameters to merge into the send request
     */
    public function buildTemplatePayload(TemplateMapping $mapping, array $resolvedVariables): array;
}
