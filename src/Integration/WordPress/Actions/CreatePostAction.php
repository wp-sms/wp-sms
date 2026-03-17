<?php

namespace WSms\Integration\WordPress\Actions;

use WSms\Flow\Contracts\AbstractAction;
use WSms\Flow\Contracts\ActionResult;
use WSms\Integration\WordPress\WordPressOptions;

defined('ABSPATH') || exit;

class CreatePostAction extends AbstractAction
{
    public function getId(): string
    {
        return 'create_post';
    }

    public function getName(): string
    {
        return __('Create Post', 'wp-sms');
    }

    public function getGroup(): string
    {
        return 'WordPress';
    }

    public function getConfigSchema(): array
    {
        return [
            'post_title' => [
                'type' => 'string',
                'label' => __('Post Title', 'wp-sms'),
                'description' => __('Title for the new post.', 'wp-sms'),
                'hint' => __('Use {{variables}} to include trigger data.', 'wp-sms'),
                'template' => true,
                'required' => true,
                'example' => 'New Post from Flow',
            ],
            'post_content' => [
                'type' => 'text',
                'label' => __('Post Content', 'wp-sms'),
                'description' => __('Content body of the new post', 'wp-sms'),
                'template' => true,
                'example' => 'This post was created automatically.',
            ],
            'post_type' => [
                'type' => 'string',
                'label' => __('Post Type', 'wp-sms'),
                'description' => __('WordPress post type', 'wp-sms'),
                'default' => 'post',
                'dynamic' => true,
                'example' => 'post',
            ],
            'post_status' => [
                'type' => 'string',
                'label' => __('Post Status', 'wp-sms'),
                'description' => __('Status of the new post', 'wp-sms'),
                'enum' => ['draft', 'publish', 'pending', 'private'],
                'default' => 'draft',
                'example' => 'draft',
            ],
        ];
    }

    public function getPlaceholders(string $triggerType): array
    {
        return match ($triggerType) {
            'wordpress.user_register' => [
                'post_title' => 'New user: {{user.display_name}}',
            ],
            default => [],
        };
    }

    public function getConfigOptions(string $fieldKey, array $context = []): array
    {
        if ($fieldKey === 'post_type') {
            return WordPressOptions::postTypes();
        }

        return [];
    }

    public function execute(array $payload, array $config): ActionResult
    {
        $title = $config['post_title'] ?? '';
        if (!$title) {
            return ActionResult::failure(__('post_title is required', 'wp-sms'));
        }

        $postId = wp_insert_post([
            'post_title' => $title,
            'post_content' => $config['post_content'] ?? '',
            'post_type' => $config['post_type'] ?? 'post',
            'post_status' => $config['post_status'] ?? 'draft',
        ], true);

        if (is_wp_error($postId)) {
            return ActionResult::failure($postId->get_error_message());
        }

        return ActionResult::success(['post_id' => $postId]);
    }
}
