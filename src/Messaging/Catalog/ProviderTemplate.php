<?php

namespace WSms\Messaging\Catalog;

defined('ABSPATH') || exit;

class ProviderTemplate
{
    /**
     * @param string $id Provider's template identifier
     * @param string $name Human-readable template name
     * @param string $language Language code (e.g. 'en')
     * @param string $category Template category (authentication, utility, etc.)
     * @param TemplateStatus $status Approval status
     * @param string $bodyText Template body with variable placeholders
     * @param int $variableCount Number of variables in the template
     * @param array $providerMeta Provider-specific metadata
     * @param array $variables Structured variable descriptors: [['key' => '1', 'type' => 'positional'], ['key' => 'otp_code', 'type' => 'named', 'label' => 'OTP Code']]
     * @param string $source Where this template came from: 'fetched' (from API) or 'manual' (admin-entered)
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $language,
        public readonly string $category,
        public readonly TemplateStatus $status,
        public readonly string $bodyText,
        public readonly int $variableCount,
        public readonly array $providerMeta = [],
        public readonly array $variables = [],
        public readonly string $source = 'fetched',
    ) {
    }

    /**
     * Get structured variable descriptors.
     *
     * When $variables is empty, auto-generates from $variableCount (positional: 1..N).
     * This provides backward compatibility with templates that only have variableCount.
     *
     * @return array<array{key: string, type: string, label?: string}>
     */
    public function getVariables(): array
    {
        if (!empty($this->variables)) {
            return $this->variables;
        }

        // Auto-generate positional variables from count
        $vars = [];
        for ($i = 1; $i <= $this->variableCount; $i++) {
            $vars[] = ['key' => (string) $i, 'type' => 'positional'];
        }
        return $vars;
    }

    public function isUsable(): bool
    {
        return $this->status->isUsable();
    }

    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'language'       => $this->language,
            'category'       => $this->category,
            'status'         => $this->status->value,
            'body_text'      => $this->bodyText,
            'variable_count' => $this->variableCount,
            'provider_meta'  => $this->providerMeta,
            'variables'      => $this->variables,
            'source'         => $this->source,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
            language: $data['language'],
            category: $data['category'],
            status: TemplateStatus::from($data['status']),
            bodyText: $data['body_text'],
            variableCount: $data['variable_count'],
            providerMeta: $data['provider_meta'] ?? [],
            variables: $data['variables'] ?? [],
            source: $data['source'] ?? 'fetched',
        );
    }
}
