<?php

namespace WSms\Integration\ContactForm7;

defined('ABSPATH') || exit;

class ContactForm7Options
{
    /** @return array<int, array{value: string, label: string}> */
    public static function forms(): array
    {
        $posts = get_posts([
            'post_type'   => 'wpcf7_contact_form',
            'numberposts' => -1,
            'orderby'     => 'title',
            'order'       => 'ASC',
        ]);

        return array_map(fn ($post) => [
            'value' => (string) $post->ID,
            'label' => $post->post_title,
        ], $posts);
    }
}
