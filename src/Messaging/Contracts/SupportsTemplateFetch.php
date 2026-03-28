<?php

namespace WSms\Messaging\Contracts;

use WSms\Messaging\Catalog\ProviderTemplate;

defined('ABSPATH') || exit;

/**
 * Extension for gateways that can fetch their template catalog from a provider API.
 *
 * Gateways that only support manual template entry implement SupportsTemplates alone.
 * Gateways with a fetch API (Twilio, MSG91, etc.) implement this interface as well.
 */
interface SupportsTemplateFetch extends SupportsTemplates
{
    /** @return ProviderTemplate[] */
    public function fetchTemplates(): array;
}
