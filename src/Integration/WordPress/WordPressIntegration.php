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
        return __('WordPress', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Core WordPress hooks for users, posts, and comments.', 'wp-sms');
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
            'user_email'   => ['label' => __('Email', 'wp-sms'),        'type' => 'core'],
            'first_name'   => ['label' => __('First Name', 'wp-sms'),   'type' => 'meta'],
            'last_name'    => ['label' => __('Last Name', 'wp-sms'),    'type' => 'meta'],
            'display_name' => ['label' => __('Display Name', 'wp-sms'), 'type' => 'core'],
            'wsms_phone'   => ['label' => __('Phone Number', 'wp-sms'), 'type' => 'meta'],
            'user_url'     => ['label' => __('Website URL', 'wp-sms'),  'type' => 'core'],
            'description'  => ['label' => __('Bio', 'wp-sms'),          'type' => 'meta'],
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
                'label'       => __('User Roles', 'wp-sms'),
                'description' => __('Only sync users with these roles. Leave empty to sync all roles.', 'wp-sms'),
            ],
            'field_mapping' => [
                'type'  => 'field_mapping',
                'label' => __('Field Mapping', 'wp-sms'),
            ],
            'auto_sync' => [
                'type'        => 'boolean',
                'label'       => __('Auto-sync', 'wp-sms'),
                'default'     => true,
                'description' => __('Automatically sync when users register, update their profile, or are deleted.', 'wp-sms'),
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
