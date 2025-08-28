<?php

namespace WP_SMS\Settings;

/**
 * Section class for organizing fields within setting groups
 */
class Section
{
    public $id;
    public $title;
    public $subtitle;
    public $helpUrl;
    public $tag;
    public $order;
    public $fields;
    public $readonly;
    public $layout; // 'default', '2-column', '3-column'

    public function __construct(array $args)
    {
        $this->id = $args['id'];
        $this->title = $args['title'];
        $this->subtitle = $args['subtitle'] ?? '';
        $this->helpUrl = $args['help_url'] ?? '';
        $this->tag = $args['tag'] ?? null;
        $this->order = $args['order'] ?? 0;
        $this->fields = $args['fields'] ?? [];
        $this->readonly = $args['readonly'] ?? false;
        $this->layout = $args['layout'] ?? 'default';
    }

    /**
     * Convert section to array for export
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'helpUrl' => $this->helpUrl,
            'tag' => $this->tag,
            'order' => $this->order,
            'fields' => array_map(fn($field) => $field->toArray(), $this->fields),
            'readonly' => $this->readonly,
            'layout' => $this->layout,
        ];
    }

    /**
     * Add a field to this section
     *
     * @param Field $field
     * @return void
     */
    public function addField(Field $field): void
    {
        $this->fields[] = $field;
    }

    /**
     * Set all fields for this section
     *
     * @param array $fields
     * @return void
     */
    public function setFields(array $fields): void
    {
        $this->fields = $fields;
    }

    /**
     * Get all fields in this section
     *
     * @return array
     */
    public function getFields(): array
    {
        $fields = $this->fields;
        // Add sub fields to the fields array
        foreach ($fields as $field) {
            if ($field->subFields) {
                $fields = array_merge($fields, $field->subFields);
            }
        }
        return $fields;
    }

    /**
     * Check if section has any fields
     *
     * @return bool
     */
    public function hasFields(): bool
    {
        return !empty($this->fields);
    }

    /**
     * Get section by ID
     *
     * @param string $id
     * @return Field|null
     */
    public function getField(string $id): ?Field
    {
        foreach ($this->fields as $field) {
            if ($field->key === $id) {
                return $field;
            }
        }
        return null;
    }
}