<?php

namespace WSms\Messaging\Catalog;

use WSms\Messaging\Contracts\SupportsTemplateCatalog;
use WSms\Messaging\Gateway\GatewayRegistry;

defined('ABSPATH') || exit;

class TemplateCatalogManager
{
    private const CACHE_KEY_PREFIX = 'wsms_template_catalog_';
    private const CACHE_TTL = 3600; // 1 hour
    private const MAPPINGS_OPTION = 'wsms_template_mappings';

    public function __construct(
        private readonly GatewayRegistry $gatewayRegistry,
    ) {
    }

    /**
     * Fetch templates from a gateway's catalog, with transient caching.
     *
     * @return ProviderTemplate[]
     * @throws TemplateCatalogException
     */
    public function getTemplates(string $gatewayId, bool $forceRefresh = false): array
    {
        $gateway = $this->resolveCatalogGateway($gatewayId);

        $cacheKey = self::CACHE_KEY_PREFIX . $gatewayId;

        if (!$forceRefresh) {
            $cached = get_transient($cacheKey);
            if (is_array($cached)) {
                return array_map([ProviderTemplate::class, 'fromArray'], $cached);
            }
        }

        $templates = $gateway->fetchTemplates();
        $serialized = array_map(fn(ProviderTemplate $t) => $t->toArray(), $templates);
        set_transient($cacheKey, $serialized, self::CACHE_TTL);

        return $templates;
    }

    /**
     * Resolve a mapping for a given template type and gateway.
     */
    public function resolveMapping(string $templateType, string $gatewayId): ?TemplateMapping
    {
        $all = $this->getAllMappings();
        $key = "{$gatewayId}:{$templateType}";

        return isset($all[$key]) ? TemplateMapping::fromArray($all[$key]) : null;
    }

    /**
     * Save or update a template mapping.
     */
    public function saveMapping(TemplateMapping $mapping): void
    {
        $all = $this->getAllMappings();
        $key = "{$mapping->gatewayId}:{$mapping->templateType}";
        $all[$key] = $mapping->toArray();
        $this->persistMappings($all);
    }

    /**
     * Remove a mapping.
     */
    public function removeMapping(string $templateType, string $gatewayId): void
    {
        $all = $this->getAllMappings();
        $key = "{$gatewayId}:{$templateType}";
        unset($all[$key]);
        $this->persistMappings($all);
    }

    /**
     * Get all mappings for a specific gateway.
     *
     * @return TemplateMapping[]
     */
    public function getMappingsForGateway(string $gatewayId): array
    {
        $all = $this->getAllMappings();
        $prefix = "{$gatewayId}:";
        $mappings = [];

        foreach ($all as $key => $data) {
            if (str_starts_with($key, $prefix)) {
                $mappings[] = TemplateMapping::fromArray($data);
            }
        }

        return $mappings;
    }

    /**
     * Verify mappings against the current catalog.
     *
     * @return array{valid: TemplateMapping[], stale: TemplateMapping[]}
     */
    public function verifyMappings(string $gatewayId): array
    {
        $mappings = $this->getMappingsForGateway($gatewayId);

        if (empty($mappings)) {
            return ['valid' => [], 'stale' => []];
        }

        try {
            $templates = $this->getTemplates($gatewayId, forceRefresh: true);
        } catch (TemplateCatalogException) {
            // Can't verify — treat all as stale
            return ['valid' => [], 'stale' => $mappings];
        }

        $templateIndex = [];
        foreach ($templates as $template) {
            $templateIndex[$template->id] = $template;
        }

        $valid = [];
        $stale = [];
        $all = $this->getAllMappings();
        $now = time();

        foreach ($mappings as $mapping) {
            $providerTemplate = $templateIndex[$mapping->providerTemplateId] ?? null;

            if ($providerTemplate && $providerTemplate->isUsable()) {
                $verified = new TemplateMapping(
                    templateType: $mapping->templateType,
                    providerTemplateId: $mapping->providerTemplateId,
                    gatewayId: $mapping->gatewayId,
                    language: $mapping->language,
                    variableMap: $mapping->variableMap,
                    providerTemplateName: $providerTemplate->name,
                    providerTemplateBody: $providerTemplate->bodyText,
                    lastVerifiedAt: $now,
                );
                $key = "{$verified->gatewayId}:{$verified->templateType}";
                $all[$key] = $verified->toArray();
                $valid[] = $verified;
            } else {
                $stale[] = $mapping;
            }
        }

        // Single write for all verified mappings
        $this->persistMappings($all);

        return ['valid' => $valid, 'stale' => $stale];
    }

    /**
     * Get the default catalog-capable gateway ID for a channel, if any.
     */
    public function getDefaultCatalogGatewayId(string $channel): ?string
    {
        $gateway = $this->gatewayRegistry->getDefault($channel);

        if ($gateway instanceof SupportsTemplateCatalog) {
            return $gateway->getId();
        }

        return null;
    }

    /**
     * Check if a gateway supports the template catalog.
     */
    public function gatewaySupportsTemplates(string $gatewayId): bool
    {
        $gateway = $this->gatewayRegistry->get($gatewayId);

        return $gateway instanceof SupportsTemplateCatalog;
    }

    private function resolveCatalogGateway(string $gatewayId): SupportsTemplateCatalog
    {
        $gateway = $this->gatewayRegistry->get($gatewayId);

        if (!$gateway instanceof SupportsTemplateCatalog) {
            throw new TemplateCatalogException(
                sprintf('Gateway "%s" does not support template catalog.', $gatewayId),
            );
        }

        return $gateway;
    }

    private ?array $mappingsCache = null;

    private function getAllMappings(): array
    {
        return $this->mappingsCache ??= get_option(self::MAPPINGS_OPTION, []);
    }

    private function persistMappings(array $all): void
    {
        $this->mappingsCache = $all;
        update_option(self::MAPPINGS_OPTION, $all);
    }
}
