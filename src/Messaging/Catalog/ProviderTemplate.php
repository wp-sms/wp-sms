<?php

namespace WSms\Messaging\Catalog;

defined('ABSPATH') || exit;

class ProviderTemplate
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $language,
        public readonly string $category,
        public readonly TemplateStatus $status,
        public readonly string $bodyText,
        public readonly int $variableCount,
        public readonly array $providerMeta = [],
    ) {
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
        );
    }
}
