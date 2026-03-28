<?php

namespace WSms\Messaging\Catalog;

defined('ABSPATH') || exit;

class TemplateMapping
{
    /**
     * @param string $templateType Internal template identifier (e.g. 'otp', 'welcome')
     * @param string $providerTemplateId Provider's template ID
     * @param string $gatewayId Which gateway this mapping is for
     * @param string $language Template language code
     * @param array $variableMap Maps internal variables to provider variable keys (e.g. ['otp_code' => '1'])
     * @param string $providerTemplateName Cached provider template name
     * @param string $providerTemplateBody Cached provider template body text
     * @param int|null $lastVerifiedAt Unix timestamp of last verification
     * @param string $source Where the mapped provider template came from: 'catalog' (fetched) or 'manual'
     * @param array $regulatoryMeta Flexible bag for regulatory IDs (DLT, etc.): ['dlt_template_id' => 'xxx', 'dlt_entity_id' => 'yyy']
     */
    public function __construct(
        public readonly string $templateType,
        public readonly string $providerTemplateId,
        public readonly string $gatewayId,
        public readonly string $language,
        public readonly array $variableMap,
        public readonly string $providerTemplateName = '',
        public readonly string $providerTemplateBody = '',
        public readonly ?int $lastVerifiedAt = null,
        public readonly string $source = 'catalog',
        public readonly array $regulatoryMeta = [],
    ) {
    }

    /**
     * Transform internal variable values to provider positional variables.
     *
     * @param array<string, string> $internalVars e.g. ['otp_code' => '482916']
     * @return array<string, string> e.g. ['1' => '482916']
     */
    public function resolveVariables(array $internalVars): array
    {
        $resolved = [];

        foreach ($this->variableMap as $internalName => $position) {
            if (isset($internalVars[$internalName])) {
                $resolved[$position] = $internalVars[$internalName];
            }
        }

        return $resolved;
    }

    /**
     * Returns names of internal variables that are not mapped to a provider position.
     *
     * @param array<string, string> $internalVars
     * @return string[]
     */
    public function getMissingVariables(array $internalVars): array
    {
        $missing = [];

        foreach ($internalVars as $name => $value) {
            if (!isset($this->variableMap[$name])) {
                $missing[] = $name;
            }
        }

        return $missing;
    }

    public function toArray(): array
    {
        return [
            'template_type'          => $this->templateType,
            'provider_template_id'   => $this->providerTemplateId,
            'gateway_id'             => $this->gatewayId,
            'language'               => $this->language,
            'variable_map'           => $this->variableMap,
            'provider_template_name' => $this->providerTemplateName,
            'provider_template_body' => $this->providerTemplateBody,
            'last_verified_at'       => $this->lastVerifiedAt,
            'source'                 => $this->source,
            'regulatory_meta'        => $this->regulatoryMeta,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            templateType: $data['template_type'],
            providerTemplateId: $data['provider_template_id'],
            gatewayId: $data['gateway_id'],
            language: $data['language'],
            variableMap: $data['variable_map'] ?? [],
            providerTemplateName: $data['provider_template_name'] ?? '',
            providerTemplateBody: $data['provider_template_body'] ?? '',
            lastVerifiedAt: $data['last_verified_at'] ?? null,
            source: $data['source'] ?? 'catalog',
            regulatoryMeta: $data['regulatory_meta'] ?? [],
        );
    }
}
