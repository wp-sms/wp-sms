<?php

namespace WSms\Integration\WordPress;

use WSms\Flow\Contracts\ActionInterface;
use WSms\Flow\Contracts\ActionResult;
use WSms\Flow\Contracts\TriggerInterface;
use WSms\Integration\Contracts\IntegrationInterface;

defined('ABSPATH') || exit;

class WordPressIntegration implements IntegrationInterface
{
    public function getId(): string
    {
        return 'wordpress';
    }

    public function getName(): string
    {
        return 'WordPress';
    }

    public function getCategory(): string
    {
        return 'cms';
    }

    public function getIcon(): string
    {
        return 'dashicons-wordpress';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function getAuthType(): string
    {
        return 'none';
    }

    public function getAuthSchema(): array
    {
        return [];
    }

    public function getTriggers(): array
    {
        return [
            new class implements TriggerInterface {
                public function getId(): string { return 'wordpress.user_register'; }
                public function getName(): string { return __('User Registered', 'wp-sms'); }
                public function getGroup(): string { return 'WordPress'; }
                public function getPayloadSchema(): array {
                    return [
                        'user_id' => ['type' => 'integer', 'label' => __('User ID', 'wp-sms')],
                        'user'    => ['type' => 'object', 'label' => __('User Data', 'wp-sms')],
                    ];
                }
                public function subscribe(callable $callback): void {
                    add_action('user_register', function (int $userId) use ($callback) {
                        $user = get_userdata($userId);
                        if ($user) {
                            $callback([
                                'user_id' => $userId,
                                'user'    => [
                                    'email'        => $user->user_email,
                                    'login'        => $user->user_login,
                                    'display_name' => $user->display_name,
                                    'roles'        => $user->roles,
                                ],
                            ]);
                        }
                    }, 20);
                }
            },
            new class implements TriggerInterface {
                public function getId(): string { return 'wordpress.post_published'; }
                public function getName(): string { return __('Post Published', 'wp-sms'); }
                public function getGroup(): string { return 'WordPress'; }
                public function getPayloadSchema(): array {
                    return [
                        'post_id'    => ['type' => 'integer', 'label' => __('Post ID', 'wp-sms')],
                        'post_title' => ['type' => 'string', 'label' => __('Post Title', 'wp-sms')],
                        'post_url'   => ['type' => 'string', 'label' => __('Post URL', 'wp-sms')],
                        'author_id'  => ['type' => 'integer', 'label' => __('Author ID', 'wp-sms')],
                    ];
                }
                public function subscribe(callable $callback): void {
                    add_action('transition_post_status', function (string $newStatus, string $oldStatus, $post) use ($callback) {
                        if ($newStatus === 'publish' && $oldStatus !== 'publish') {
                            $callback([
                                'post_id'    => $post->ID,
                                'post_title' => $post->post_title,
                                'post_url'   => get_permalink($post->ID),
                                'post_type'  => $post->post_type,
                                'author_id'  => (int) $post->post_author,
                            ]);
                        }
                    }, 10, 3);
                }
            },
            new class implements TriggerInterface {
                public function getId(): string { return 'wordpress.comment_posted'; }
                public function getName(): string { return __('Comment Posted', 'wp-sms'); }
                public function getGroup(): string { return 'WordPress'; }
                public function getPayloadSchema(): array {
                    return [
                        'comment_id' => ['type' => 'integer', 'label' => __('Comment ID', 'wp-sms')],
                        'post_id'    => ['type' => 'integer', 'label' => __('Post ID', 'wp-sms')],
                        'author'     => ['type' => 'string', 'label' => __('Author', 'wp-sms')],
                        'email'      => ['type' => 'string', 'label' => __('Email', 'wp-sms')],
                    ];
                }
                public function subscribe(callable $callback): void {
                    add_action('comment_post', function (int $commentId, $approved) use ($callback) {
                        $comment = get_comment($commentId);
                        if ($comment) {
                            $callback([
                                'comment_id' => $commentId,
                                'post_id'    => (int) $comment->comment_post_ID,
                                'author'     => $comment->comment_author,
                                'email'      => $comment->comment_author_email,
                                'content'    => $comment->comment_content,
                                'approved'   => $approved,
                            ]);
                        }
                    }, 10, 2);
                }
            },
        ];
    }

    public function getActions(): array
    {
        return [];
    }

    public function boot(): void
    {
        // No additional boot needed — triggers subscribe via add_action
    }
}
