<?php

namespace WSms\Auth\ValueObjects;

defined('ABSPATH') || exit;

class ProfileFieldDefinition
{
    public const TYPE_TEXT = 'text';
    public const TYPE_TEXTAREA = 'textarea';
    public const TYPE_SELECT = 'select';
    public const TYPE_CHECKBOX = 'checkbox';

    public const VALID_TYPES = [self::TYPE_TEXT, self::TYPE_TEXTAREA, self::TYPE_SELECT, self::TYPE_CHECKBOX];

    public const SOURCE_SYSTEM = 'system';
    public const SOURCE_CUSTOM = 'custom';
    public const SOURCE_META = 'meta';

    public const VALID_SOURCES = [self::SOURCE_SYSTEM, self::SOURCE_CUSTOM, self::SOURCE_META];

    public const VISIBILITY_REGISTRATION = 'registration';
    public const VISIBILITY_PROFILE = 'profile';
    public const VISIBILITY_BOTH = 'both';
    public const VISIBILITY_HIDDEN = 'hidden';

    public const VALID_VISIBILITY = [self::VISIBILITY_REGISTRATION, self::VISIBILITY_PROFILE, self::VISIBILITY_BOTH, self::VISIBILITY_HIDDEN];

    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $label,
        public readonly string $source,
        public readonly string $metaKey,
        public readonly string $visibility = self::VISIBILITY_BOTH,
        public readonly bool $required = false,
        public readonly int $sortOrder = 10,
        public readonly string $placeholder = '',
        public readonly array $options = [],
        public readonly string $description = '',
        public readonly mixed $defaultValue = '',
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? '',
            type: $data['type'] ?? self::TYPE_TEXT,
            label: $data['label'] ?? '',
            source: $data['source'] ?? self::SOURCE_CUSTOM,
            metaKey: $data['meta_key'] ?? $data['id'] ?? '',
            visibility: $data['visibility'] ?? self::VISIBILITY_BOTH,
            required: !empty($data['required']),
            sortOrder: (int) ($data['sort_order'] ?? 10),
            placeholder: $data['placeholder'] ?? '',
            options: $data['options'] ?? [],
            description: $data['description'] ?? '',
            defaultValue: $data['default_value'] ?? '',
        );
    }

    public function toArray(): array
    {
        $data = [
            'id'         => $this->id,
            'type'       => $this->type,
            'label'      => $this->label,
            'source'     => $this->source,
            'meta_key'   => $this->metaKey,
            'visibility' => $this->visibility,
            'required'   => $this->required,
            'sort_order' => $this->sortOrder,
        ];

        if ($this->placeholder !== '') {
            $data['placeholder'] = $this->placeholder;
        }

        if (!empty($this->options)) {
            $data['options'] = $this->options;
        }

        if ($this->description !== '') {
            $data['description'] = $this->description;
        }

        if ($this->defaultValue !== '') {
            $data['default_value'] = $this->defaultValue;
        }

        return $data;
    }

    public function isSystem(): bool
    {
        return $this->source === self::SOURCE_SYSTEM;
    }

    public function isVisibleIn(string $context): bool
    {
        return $this->visibility === self::VISIBILITY_BOTH || $this->visibility === $context;
    }
}
