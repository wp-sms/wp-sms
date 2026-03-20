<?php

namespace WSms\Messaging\Template\ValueObjects;

defined('ABSPATH') || exit;

class VariableDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly string $description,
        public readonly bool $required,
        public readonly string $example,
    ) {
    }

    public function toArray(): array
    {
        return [
            'name'        => $this->name,
            'label'       => $this->label,
            'description' => $this->description,
            'required'    => $this->required,
            'example'     => $this->example,
        ];
    }
}
