<?php

namespace WP_SMS\Settings;

/**
 * FieldGroup class for grouping fields within repeatable structures
 */
class FieldGroup
{
    public $key;
    public $label;
    public $description;
    public $fields;
    public $order;
    public $layout; // 'default', '2-column', '3-column'

    public function __construct(array $args)
    {
        $this->key = $args['key'];
        $this->label = $args['label'] ?? '';
        $this->description = $args['description'] ?? '';
        $this->fields = $args['fields'] ?? [];
        $this->order = $args['order'] ?? 0;
        $this->layout = $args['layout'] ?? 'default';
    }

    /**
     * Convert field group to array for export
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'description' => $this->description,
            'fields' => array_map(fn($field) => $field->toArray(), $this->fields),
            'order' => $this->order,
            'layout' => $this->layout,
        ];
    }

    /**
     * Add a field to this group
     *
     * @param Field $field
     * @return void
     */
    public function addField(Field $field): void
    {
        $this->fields[] = $field;
    }

    /**
     * Get all fields in this group
     *
     * @return array
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * Check if group has any fields
     *
     * @return bool
     */
    public function hasFields(): bool
    {
        return !empty($this->fields);
    }

    /**
     * Get field by key
     *
     * @param string $key
     * @return Field|null
     */
    public function getField(string $key): ?Field
    {
        foreach ($this->fields as $field) {
            if ($field->key === $key) {
                return $field;
            }
        }
        return null;
    }
} 