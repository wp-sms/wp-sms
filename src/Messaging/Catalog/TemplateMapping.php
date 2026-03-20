<?php

namespace WSms\Messaging\Catalog;

defined('ABSPATH') || exit;

class TemplateMapping
{
    public function __construct(
        public readonly string $templateType,
        public readonly string $providerTemplateId,
        public readonly string $gatewayId,
        public readonly string $language,
        public readonly array $variableMap,
        public readonly string $providerTemplateName = '',
        public readonly string $providerTemplateBody = '',
        public readonly ?int $lastVerifiedAt = null,
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
        );
    }
}
