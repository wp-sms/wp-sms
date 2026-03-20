<?php

namespace WSms\Messaging\Contracts;

use WSms\Messaging\Catalog\ProviderTemplate;
use WSms\Messaging\Catalog\TemplateMapping;

defined('ABSPATH') || exit;

interface SupportsTemplateCatalog
{
    /** @return ProviderTemplate[] */
    public function fetchTemplates(): array;

    public function requiresTemplateForChannel(string $channel): bool;

    /**
     * Build the provider-specific API payload for a template-based message.
     *
     * @return array Provider-specific parameters to merge into the send request
     */
    public function buildTemplatePayload(TemplateMapping $mapping, array $resolvedVariables): array;
}
