<?php

namespace WP_SMS\Settings;

class Field
{
    public $key;
    public $type;
    public $label;
    public $description;
    public $default;
    public $groupLabel;
    public $options;
    public $section;
    public $order;
    public $doc;
    public $showIf;
    public $hideIf;
    public $validateCallback;
    public $sanitizeCallback;
    public $repeatable;
    public $tag;
    public $readonly;
    public $optionsDependsOn;
    public $sortable;

    public function __construct(array $args)
    {
        $this->key              = $args['key'];
        $this->type             = $args['type'];
        $this->label            = $args['label'] ?? '';
        $this->description      = $args['description'] ?? '';
        $this->default          = $args['default'] ?? null;
        $this->groupLabel       = $args['group_label'] ?? null;
        $this->section          = $args['section'] ?? null;
        $this->options          = $args['options'] ?? [];
        $this->order            = $args['order'] ?? 0;
        $this->doc              = $args['doc'] ?? '';
        $this->showIf           = $args['show_if'] ?? null;
        $this->hideIf           = $args['hide_if'] ?? null;
        $this->validateCallback = $args['validate_callback'] ?? FieldValidator::get($this->type);
        $this->sanitizeCallback = $args['sanitize_callback'] ?? null;
        $this->repeatable       = $args['repeatable'] ?? false;
        $this->tag              = $args['tag'] ?? null;
        $this->readonly         = $args['readonly'] ?? false;
        $this->optionsDependsOn = $args['options_depends_on'] ?? null;
        $this->sortable         = $args['sortable'] ?? false;
    }

    public function toArray(): array
    {
        return [
            'key'         => $this->key,
            'type'        => $this->type,
            'label'       => $this->label,
            'description' => $this->description,
            'default'     => $this->default,
            'groupLabel'  => $this->groupLabel,
            'section'     => $this->section,
            'options'     => $this->options,
            'order'       => $this->order,
            'doc'         => $this->doc,
            'showIf'      => $this->showIf,
            'hideIf'      => $this->hideIf,
            'repeatable'  => $this->repeatable,
            'tag'         => $this->tag,
            'readonly'    => $this->readonly,
            'options_depends_on' => $this->optionsDependsOn,
            'sortable'    => $this->sortable,
            // Exclude callbacks from output to avoid serializing closures
        ];
    }


    public function getKey()
    {
        return $this->key;
    }


    public function getValidateCallback(): ?callable
    {
        return is_callable($this->validateCallback) ? $this->validateCallback : null;
    }

    public function getSanitizeCallback(): ?callable
    {
        return is_callable($this->sanitizeCallback) ? $this->sanitizeCallback : null;
    }

    public function getOptions()
    {
        return $this->options ?? [];
    }

    /**
     * Check if field has a tag
     *
     * @return bool
     */
    public function hasTag(): bool
    {
        return !empty($this->tag);
    }

    /**
     * Check if field is readonly
     *
     * @return bool
     */
    public function isReadonly(): bool
    {
        return $this->readonly;
    }
}
