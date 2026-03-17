<?php

namespace WSms\Integration\WordPress;

defined('ABSPATH') || exit;

class WordPressOptions
{
    /** @return array<int, array{value: string, label: string}> */
    public static function postTypes(): array
    {
        $types = get_post_types(['public' => true], 'objects');

        return array_map(fn ($type) => [
            'value' => $type->name,
            'label' => $type->labels->singular_name,
        ], array_values($types));
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function roles(): array
    {
        $roles = wp_roles()->get_names();

        return array_map(fn ($label, $slug) => [
            'value' => $slug,
            'label' => $label,
        ], $roles, array_keys($roles));
    }
}
