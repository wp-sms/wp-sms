<?php

namespace WSms\Integration\WordPress\Triggers;

use WSms\Flow\Contracts\AbstractTrigger;

defined('ABSPATH') || exit;

class CommentPostedTrigger extends AbstractTrigger
{
    public function getId(): string
    {
        return 'wordpress.comment_posted';
    }

    public function getName(): string
    {
        return __('Comment Posted', 'wp-sms');
    }

    public function getGroup(): string
    {
        return 'WordPress';
    }

    public function getPayloadSchema(): array
    {
        return [
            'comment_id' => [
                'type' => 'integer',
                'label' => __('Comment ID', 'wp-sms'),
                'description' => __('The WordPress comment ID', 'wp-sms'),
                'example' => 15,
            ],
            'post_id' => [
                'type' => 'integer',
                'label' => __('Post ID', 'wp-sms'),
                'description' => __('The post the comment was left on', 'wp-sms'),
                'example' => 123,
            ],
            'author' => [
                'type' => 'string',
                'label' => __('Author', 'wp-sms'),
                'description' => __('The comment author name', 'wp-sms'),
                'example' => 'Jane Doe',
            ],
            'email' => [
                'type' => 'string',
                'label' => __('Email', 'wp-sms'),
                'format' => 'email',
                'description' => __('The comment author email', 'wp-sms'),
                'example' => 'jane@example.com',
            ],
            'content' => [
                'type' => 'string',
                'label' => __('Content', 'wp-sms'),
                'description' => __('The comment content', 'wp-sms'),
                'example' => 'Great article!',
            ],
            'approved' => [
                'type' => 'string',
                'label' => __('Approved', 'wp-sms'),
                'description' => __('Whether the comment is approved (1) or pending (0)', 'wp-sms'),
                'example' => '1',
            ],
        ];
    }

    public function getFilterSchema(): array
    {
        return [
            'approved' => [
                'type'        => 'string',
                'label'       => __('Approval Status', 'wp-sms'),
                'description' => __('Only trigger for this approval status', 'wp-sms'),
                'enum'        => ['0', '1'],
                'enumLabels'  => [
                    '0' => __('Pending', 'wp-sms'),
                    '1' => __('Approved', 'wp-sms'),
                ],
            ],
        ];
    }

    public function subscribe(callable $callback): void
    {
        add_action('comment_post', function (int $commentId, $approved) use ($callback) {
            $comment = get_comment($commentId);
            if ($comment) {
                $callback([
                    'comment_id' => $commentId,
                    'post_id' => (int) $comment->comment_post_ID,
                    'author' => $comment->comment_author,
                    'email' => $comment->comment_author_email,
                    'content' => $comment->comment_content,
                    'approved' => (string) (int) $approved,
                ]);
            }
        }, 10, 2);
    }
}
