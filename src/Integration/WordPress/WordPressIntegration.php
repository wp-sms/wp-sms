<?php

namespace WSms\Integration\WordPress;

use WSms\Integration\Contracts\IntegrationInterface;
use WSms\Integration\WordPress\Actions\CreatePostAction;
use WSms\Integration\WordPress\Actions\DeleteUserAction;
use WSms\Integration\WordPress\Actions\SetUserRoleAction;
use WSms\Integration\WordPress\Actions\UpdateUserMetaAction;
use WSms\Integration\WordPress\Triggers\CommentPostedTrigger;
use WSms\Integration\WordPress\Triggers\PostPublishedTrigger;
use WSms\Integration\WordPress\Triggers\PostStatusChangedTrigger;
use WSms\Integration\WordPress\Triggers\UserDeletedTrigger;
use WSms\Integration\WordPress\Triggers\UserRegisterTrigger;
use WSms\Integration\WordPress\Triggers\UserRoleChangedTrigger;
use WSms\Integration\WordPress\Triggers\UserUpdatedTrigger;

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

    public function getDescription(): string
    {
        return 'Core WordPress hooks for users, posts, and comments.';
    }

    public function getCategory(): string
    {
        return 'cms';
    }

    public function getIcon(): string
    {
        return 'globe';
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
            new UserRegisterTrigger(),
            new PostPublishedTrigger(),
            new CommentPostedTrigger(),
            new UserUpdatedTrigger(),
            new UserDeletedTrigger(),
            new UserRoleChangedTrigger(),
            new PostStatusChangedTrigger(),
        ];
    }

    public function getActions(): array
    {
        return [
            new UpdateUserMetaAction(),
            new SetUserRoleAction(),
            new CreatePostAction(),
            new DeleteUserAction(),
        ];
    }

    public function getCapabilities(): array
    {
        return [];
    }

    public function boot(): void
    {
    }

    public function connect(array $credentials): array
    {
        return $credentials;
    }

    public function disconnect(): void
    {
    }

    public function isConnected(): bool
    {
        return true;
    }
}
