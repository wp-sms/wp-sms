<?php

namespace WSms\Messaging\Catalog;

use WSms\Messaging\Contracts\SupportsTemplateFetch;
use WSms\Messaging\Contracts\SupportsTemplates;
use WSms\Messaging\Gateway\GatewayRegistry;

defined('ABSPATH') || exit;

class TemplateCatalogManager
{
    private const CACHE_KEY_PREFIX = 'wsms_template_catalog_';
    private const CACHE_TTL = 3600; // 1 hour
    private const MAPPINGS_OPTION = 'wsms_template_mappings';
    private const MANUAL_TEMPLATES_OPTION = 'wsms_manual_templates';

    public function __construct(
        private readonly GatewayRegistry $gatewayRegistry,
    ) {
    }

    /**
     * Get all templates for a gateway — merges fetched (API) + manual templates.
     *
     * For SupportsTemplateFetch gateways: fetches from API (cached) + merges manuals.
     * For SupportsTemplates-only gateways: returns manual templates only.
     *
     * @return ProviderTemplate[]
     * @throws TemplateCatalogException
     */
    public function getTemplates(string $gatewayId, bool $forceRefresh = false): array
    {
        $gateway = $this->resolveTemplateGateway($gatewayId);

        $fetched = [];

        if ($gateway instanceof SupportsTemplateFetch) {
            $cacheKey = self::CACHE_KEY_PREFIX . $gatewayId;

            if (!$forceRefresh) {
                $cached = get_transient($cacheKey);
                if (is_array($cached)) {
                    $fetched = array_map([ProviderTemplate::class, 'fromArray'], $cached);
                }
            }

            if (empty($fetched) || $forceRefresh) {
                $fetched = $gateway->fetchTemplates();
                $serialized = array_map(fn(ProviderTemplate $t) => $t->toArray(), $fetched);
                set_transient($cacheKey, $serialized, self::CACHE_TTL);
            }
        }

        $manuals = $this->getManualTemplates($gatewayId);

        return array_merge($fetched, $manuals);
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
     * Manual-source mappings are always treated as valid (can't be auto-verified).
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
            // Manual-source mappings can't be auto-verified — always treat as valid
            if ($mapping->source === 'manual') {
                $valid[] = $mapping;
                continue;
            }

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
                    source: $mapping->source,
                    regulatoryMeta: $mapping->regulatoryMeta,
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
     * Get the default template-capable gateway ID for a channel, if any.
     */
    public function getDefaultCatalogGatewayId(string $channel): ?string
    {
        $gateway = $this->gatewayRegistry->getDefault($channel);

        if ($gateway instanceof SupportsTemplates) {
            return $gateway->getId();
        }

        return null;
    }

    /**
     * Check if a gateway supports templates (either fetchable or manual-only).
     */
    public function gatewaySupportsTemplates(string $gatewayId): bool
    {
        $gateway = $this->gatewayRegistry->get($gatewayId);

        return $gateway instanceof SupportsTemplates;
    }

    /**
     * Check if a gateway can fetch templates from a provider API.
     */
    public function gatewaySupportsTemplateFetch(string $gatewayId): bool
    {
        $gateway = $this->gatewayRegistry->get($gatewayId);

        return $gateway instanceof SupportsTemplateFetch;
    }

    /**
     * Get template capabilities for a gateway.
     *
     * @return array{supports_templates: bool, fetchable: bool, variable_style: string|null, required_channels: string[]}|null
     */
    public function getTemplateCapabilities(string $gatewayId): ?array
    {
        $gateway = $this->gatewayRegistry->get($gatewayId);

        if (!$gateway instanceof SupportsTemplates) {
            return null;
        }

        $requiredChannels = [];
        foreach ($gateway->getSupportedChannels() as $channel) {
            if ($gateway->requiresTemplateForChannel($channel)) {
                $requiredChannels[] = $channel;
            }
        }

        return [
            'supports_templates' => true,
            'fetchable' => $gateway instanceof SupportsTemplateFetch,
            'variable_style' => $gateway->getVariableStyle()->value,
            'required_channels' => $requiredChannels,
        ];
    }

    private function resolveTemplateGateway(string $gatewayId): SupportsTemplates
    {
        $gateway = $this->gatewayRegistry->get($gatewayId);

        if (!$gateway instanceof SupportsTemplates) {
            throw new TemplateCatalogException(
                sprintf('Gateway "%s" does not support templates.', $gatewayId),
            );
        }

        return $gateway;
    }

    // --- Manual Template CRUD ---

    /**
     * Save a manually entered provider template.
     */
    public function saveManualTemplate(string $gatewayId, ProviderTemplate $template): void
    {
        $all = $this->getAllManualTemplates();
        $all[$gatewayId][$template->id] = $template->toArray();
        $this->persistManualTemplates($all);
    }

    /**
     * Remove a manually entered provider template.
     */
    public function removeManualTemplate(string $gatewayId, string $templateId): void
    {
        $all = $this->getAllManualTemplates();
        unset($all[$gatewayId][$templateId]);

        if (empty($all[$gatewayId])) {
            unset($all[$gatewayId]);
        }

        $this->persistManualTemplates($all);
    }

    /**
     * Get all manual templates for a gateway.
     *
     * @return ProviderTemplate[]
     */
    public function getManualTemplates(string $gatewayId): array
    {
        $all = $this->getAllManualTemplates();
        $gatewayTemplates = $all[$gatewayId] ?? [];

        return array_map([ProviderTemplate::class, 'fromArray'], array_values($gatewayTemplates));
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

    private ?array $manualTemplatesCache = null;

    private function getAllManualTemplates(): array
    {
        return $this->manualTemplatesCache ??= get_option(self::MANUAL_TEMPLATES_OPTION, []);
    }

    private function persistManualTemplates(array $all): void
    {
        $this->manualTemplatesCache = $all;
        update_option(self::MANUAL_TEMPLATES_OPTION, $all);
    }
}
