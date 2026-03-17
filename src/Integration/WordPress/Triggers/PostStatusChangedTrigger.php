<?php

namespace WSms\Integration\WordPress\Triggers;

use WSms\Flow\Contracts\AbstractTrigger;

defined('ABSPATH') || exit;

class PostStatusChangedTrigger extends AbstractTrigger
{
    public function getId(): string
    {
        return 'wordpress.post_status_changed';
    }

    public function getName(): string
    {
        return __('Post Status Changed', 'wp-sms');
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
                'description' => __('The title of the post', 'wp-sms'),
                'example' => 'Hello World',
            ],
            'post_type' => [
                'type' => 'string',
                'label' => __('Post Type', 'wp-sms'),
                'description' => __('The post type (post, page, etc.)', 'wp-sms'),
                'example' => 'post',
            ],
            'old_status' => [
                'type' => 'string',
                'label' => __('Old Status', 'wp-sms'),
                'description' => __('The previous status of the post', 'wp-sms'),
                'example' => 'draft',
            ],
            'new_status' => [
                'type' => 'string',
                'label' => __('New Status', 'wp-sms'),
                'description' => __('The new status of the post', 'wp-sms'),
                'example' => 'publish',
            ],
        ];
    }

    public function subscribe(callable $callback): void
    {
        add_action('transition_post_status', function (string $newStatus, string $oldStatus, $post) use ($callback) {
            if ($newStatus === $oldStatus) {
                return;
            }

            $callback([
                'post_id' => $post->ID,
                'post_title' => $post->post_title,
                'post_type' => $post->post_type,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]);
        }, 10, 3);
    }
}
