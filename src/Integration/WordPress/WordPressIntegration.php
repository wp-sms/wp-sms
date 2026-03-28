<?php

namespace WSms\Integration\WordPress;

use WSms\Contact\Contracts\ContactRepositoryInterface;
use WSms\Integration\Contracts\ContactImportHelper;
use WSms\Integration\Contracts\IntegrationCapability;
use WSms\Integration\Contracts\IntegrationInterface;
use WSms\Integration\Contracts\SupportsContactImport;
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

class WordPressIntegration implements IntegrationInterface, SupportsContactImport
{
    public function __construct(
        private readonly ?ContactRepositoryInterface $contacts = null,
    ) {
    }

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
        return [
            ['id' => IntegrationCapability::CONTACT_IMPORT, 'supported' => true],
        ];
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

    // --- SupportsContactImport ---

    public function getAvailableImportFields(): array
    {
        return [
            'user_email'   => ['label' => 'Email',        'type' => 'core'],
            'first_name'   => ['label' => 'First Name',   'type' => 'meta'],
            'last_name'    => ['label' => 'Last Name',    'type' => 'meta'],
            'display_name' => ['label' => 'Display Name', 'type' => 'core'],
            'wsms_phone'   => ['label' => 'Phone Number', 'type' => 'meta'],
            'user_url'     => ['label' => 'Website URL',  'type' => 'core'],
            'description'  => ['label' => 'Bio',          'type' => 'meta'],
        ];
    }

    public function getDefaultImportFieldMapping(): array
    {
        return [
            'email'      => 'user_email',
            'first_name' => 'first_name',
            'last_name'  => 'last_name',
            'phone'      => 'wsms_phone',
        ];
    }

    public function getImportConfigSchema(): array
    {
        return [
            'roles' => [
                'type'        => 'multi_select',
                'label'       => 'User Roles',
                'description' => 'Only sync users with these roles. Leave empty to sync all roles.',
            ],
            'field_mapping' => [
                'type'  => 'field_mapping',
                'label' => 'Field Mapping',
            ],
            'auto_sync' => [
                'type'        => 'boolean',
                'label'       => 'Auto-sync',
                'default'     => true,
                'description' => 'Automatically sync when users register, update their profile, or are deleted.',
            ],
        ];
    }

    public function importOne(mixed $externalId, array $config, bool $suppressEvents = false): ?string
    {
        $user = get_userdata((int) $externalId);
        if (!$user) {
            return null;
        }

        $roles = $config['roles'] ?? [];
        if (!empty($roles) && empty(array_intersect($user->roles, $roles))) {
            return null;
        }

        if (empty($user->user_email)) {
            return null;
        }

        $fieldMapping = $config['field_mapping'] ?? $this->getDefaultImportFieldMapping();
        $sourceData = $this->extractUserData($user);
        $contactData = ContactImportHelper::applyFieldMapping($sourceData, $fieldMapping);
        $contactData['wp_user_id'] = $user->ID;
        $contactData['source'] = $this->getId();

        if (get_user_meta($user->ID, 'wsms_email_verified', true)) {
            $contactData['email_verified'] = 1;
        }
        if (get_user_meta($user->ID, 'wsms_phone_verified', true)) {
            $contactData['phone_verified'] = 1;
        }

        return ContactImportHelper::upsertContact($this->contacts, $contactData, $suppressEvents);
    }

    public function getImportBatch(array $config, int $batchSize, mixed $afterCursor = null): array
    {
        $args = [
            'number'  => $batchSize,
            'orderby' => 'ID',
            'order'   => 'ASC',
            'fields'  => ['ID'],
        ];

        $roles = $config['roles'] ?? [];
        if (!empty($roles)) {
            $args['role__in'] = $roles;
        }

        $callback = null;
        if ($afterCursor !== null) {
            add_action('pre_user_query', $callback = function ($query) use ($afterCursor) {
                $query->query_where .= " AND {$query->db->users}.ID > " . (int) $afterCursor;
            });
        }

        try {
            $users = get_users($args);
        } finally {
            if ($callback !== null) {
                remove_action('pre_user_query', $callback);
            }
        }

        return array_map(fn($u) => $u->ID, $users);
    }

    public function countImportable(array $config): int
    {
        $args = [
            'fields'      => ['ID'],
            'count_total' => true,
            'number'      => 0,
        ];

        $roles = $config['roles'] ?? [];
        if (!empty($roles)) {
            $args['role__in'] = $roles;
        }

        $query = new \WP_User_Query($args);

        return (int) $query->get_total();
    }

    public function handleImportDeletion(mixed $externalId): void
    {
        $contact = $this->contacts->findByWpUser((int) $externalId);
        if ($contact) {
            $this->contacts->delete($contact['id']);
        }
    }

    private function extractUserData(\WP_User $user): array
    {
        $data = [
            'user_email'   => $user->user_email,
            'display_name' => $user->display_name,
            'user_url'     => $user->user_url,
        ];

        $metaFields = ['first_name', 'last_name', 'wsms_phone', 'description'];
        foreach ($metaFields as $field) {
            $value = get_user_meta($user->ID, $field, true);
            $data[$field] = ($value !== '' && $value !== false) ? $value : null;
        }

        return $data;
    }
}
