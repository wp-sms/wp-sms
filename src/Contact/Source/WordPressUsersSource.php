<?php

namespace WSms\Contact\Source;

defined('ABSPATH') || exit;

class WordPressUsersSource extends AbstractContactSource
{
    public function getType(): string
    {
        return 'wordpress_users';
    }

    public function getName(): string
    {
        return 'WordPress Users';
    }

    public function getDescription(): string
    {
        return 'Sync contacts from your WordPress user database, including phone numbers and custom meta fields.';
    }

    public function getIcon(): string
    {
        return 'wordpress';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function getDefaultFieldMapping(): array
    {
        return [
            'email'      => 'user_email',
            'first_name' => 'first_name',
            'last_name'  => 'last_name',
            'phone'      => 'wsms_phone',
        ];
    }

    public function getAvailableFields(): array
    {
        return [
            'user_email'   => ['label' => 'Email',        'type' => 'core'],
            'first_name'   => ['label' => 'First Name',   'type' => 'meta'],
            'last_name'    => ['label' => 'Last Name',     'type' => 'meta'],
            'display_name' => ['label' => 'Display Name',  'type' => 'core'],
            'wsms_phone'   => ['label' => 'Phone Number',  'type' => 'meta'],
            'user_url'     => ['label' => 'Website URL',   'type' => 'core'],
            'description'  => ['label' => 'Bio',           'type' => 'meta'],
        ];
    }

    public function getConfigSchema(): array
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
                'type'    => 'boolean',
                'label'   => 'Auto-sync',
                'default' => true,
                'description' => 'Automatically sync when users register, update their profile, or are deleted.',
            ],
        ];
    }

    public function syncOne(mixed $externalId, array $config, bool $suppressEvents = false): ?string
    {
        $user = get_userdata((int) $externalId);
        if (!$user) {
            return null;
        }

        // Role filter: skip users not in configured roles
        $roles = $config['roles'] ?? [];
        if (!empty($roles) && empty(array_intersect($user->roles, $roles))) {
            return null;
        }

        // Skip users with no email (UNIQUE constraint)
        if (empty($user->user_email)) {
            return null;
        }

        $fieldMapping = $config['field_mapping'] ?? $this->getDefaultFieldMapping();

        // Build source data from WP user
        $sourceData = $this->extractUserData($user);

        // Apply field mapping
        $contactData = $this->applyFieldMapping($sourceData, $fieldMapping);
        $contactData['wp_user_id'] = $user->ID;
        $contactData['source'] = $this->getType();

        return $this->upsertContact($contactData, $suppressEvents);
    }

    public function getBatch(array $config, int $batchSize, ?int $afterId): array
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
        if ($afterId !== null) {
            add_action('pre_user_query', $callback = function ($query) use ($afterId) {
                $query->query_where .= " AND {$query->db->users}.ID > " . (int) $afterId;
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

    public function countAvailable(array $config): int
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

    public function handleDeletion(mixed $externalId): void
    {
        $contact = $this->contacts->findByWpUser((int) $externalId);
        if ($contact) {
            $this->contacts->update($contact['id'], ['status' => 'unsubscribed']);
        }
    }

    /**
     * Extract all available data from a WP_User object.
     */
    private function extractUserData(\WP_User $user): array
    {
        $data = [
            'user_email'   => $user->user_email,
            'display_name' => $user->display_name,
            'user_url'     => $user->user_url,
        ];

        // Meta fields
        $metaFields = ['first_name', 'last_name', 'wsms_phone', 'description'];
        foreach ($metaFields as $field) {
            $value = get_user_meta($user->ID, $field, true);
            $data[$field] = ($value !== '' && $value !== false) ? $value : null;
        }

        return $data;
    }
}
