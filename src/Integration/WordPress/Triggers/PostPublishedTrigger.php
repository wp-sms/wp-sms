<?php

namespace WSms\Integration\WordPress\Triggers;

use WSms\Flow\Contracts\AbstractTrigger;

defined('ABSPATH') || exit;

class PostPublishedTrigger extends AbstractTrigger
{
    public function getId(): string
    {
        return 'wordpress.post_published';
    }

    public function getName(): string
    {
        return __('Post Published', 'wp-sms');
    }

    public function getGroup(): string
    {
        return 'WordPress';
    }

    public function getPayloadSchema(): array
    {
        return [
            'post_id' => [
                'type' => 'integer',
                'label' => __('Post ID', 'wp-sms'),
                'description' => __('The WordPress post ID', 'wp-sms'),
                'example' => 123,
            ],
            'post_title' => [
                'type' => 'string',
                'label' => __('Post Title', 'wp-sms'),
                'description' => __('The title of the published post', 'wp-sms'),
                'example' => 'Hello World',
            ],
            'post_url' => [
                'type' => 'string',
                'label' => __('Post URL', 'wp-sms'),
                'format' => 'url',
                'description' => __('The permalink of the published post', 'wp-sms'),
                'example' => 'https://example.com/hello-world',
            ],
            'post_type' => [
                'type' => 'string',
                'label' => __('Post Type', 'wp-sms'),
                'description' => __('The post type (post, page, etc.)', 'wp-sms'),
                'example' => 'post',
            ],
            'author_id' => [
                'type' => 'integer',
                'label' => __('Author ID', 'wp-sms'),
                'description' => __('The user ID of the post author', 'wp-sms'),
                'example' => 1,
            ],
        ];
    }

    public function subscribe(callable $callback): void
    {
        add_action('transition_post_status', function (string $newStatus, string $oldStatus, $post) use ($callback) {
            if ($newStatus === 'publish' && $oldStatus !== 'publish') {
                $callback([
                    'post_id' => $post->ID,
                    'post_title' => $post->post_title,
                    'post_url' => get_permalink($post->ID),
                    'post_type' => $post->post_type,
                    'author_id' => (int) $post->post_author,
                ]);
            }
        }, 10, 3);
    }
}
