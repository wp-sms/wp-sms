<?php

namespace WSms\Auth;

use WSms\Dependencies\Symfony\Component\Uid\Ulid;

defined('ABSPATH') || exit;

class RegistrationForm
{
    public function __construct(
        private string $id,
        private string $name,
        private string $slug,
        private array $fields = [],
        private array $authOverrides = [],
        private string $userRole = '',
        private string $redirectUrl = '',
        private array $branding = [],
        private string $status = 'active',
        private ?string $description = null,
        private ?int $createdBy = null,
        private ?string $createdAt = null,
        private ?string $updatedAt = null,
    ) {
        if ($this->id === '') {
            $this->id = (string) new Ulid();
        }
        if ($this->createdAt === null) {
            $this->createdAt = gmdate('c');
        }
        if ($this->updatedAt === null) {
            $this->updatedAt = $this->createdAt;
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function getAuthOverrides(): array
    {
        return $this->authOverrides;
    }

    public function getUserRole(): string
    {
        return $this->userRole;
    }

    public function getRedirectUrl(): string
    {
        return $this->redirectUrl;
    }

    public function getBrandingOverrides(): array
    {
        return $this->branding;
    }

    /**
     * Get the list of field IDs defined for this form.
     *
     * @return string[]
     */
    public function getFieldIds(): array
    {
        return array_column($this->fields, 'id');
    }

    /**
     * Check if a field is included in this form.
     */
    public function hasField(string $fieldId): bool
    {
        return in_array($fieldId, $this->getFieldIds(), true);
    }

    /**
     * Check if a specific field is required in this form.
     */
    public function isFieldRequired(string $fieldId): bool
    {
        foreach ($this->fields as $field) {
            if ($field['id'] === $fieldId) {
                return !empty($field['required']);
            }
        }

        return false;
    }

    /**
     * Apply form auth overrides onto a base settings array.
     *
     * Only verify_at_signup and required_at_signup can be overridden,
     * and only for channels that are enabled in the base settings.
     */
    public static function applyOverrides(array $settings, array $overrides): array
    {
        foreach ($overrides as $channelKey => $channelOverrides) {
            if (!isset($settings[$channelKey]) || empty($settings[$channelKey]['enabled'])) {
                continue;
            }

            foreach ($channelOverrides as $key => $value) {
                if (!in_array($key, ['verify_at_signup', 'required_at_signup'], true)) {
                    continue;
                }

                $settings[$channelKey][$key] = (bool) $value;
            }
        }

        return $settings;
    }

    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'slug'           => $this->slug,
            'description'    => $this->description,
            'status'         => $this->status,
            'fields'         => $this->fields,
            'auth_overrides' => $this->authOverrides,
            'user_role'      => $this->userRole,
            'redirect_url'   => $this->redirectUrl,
            'branding'       => $this->branding,
            'created_by'     => $this->createdBy,
            'created_at'     => $this->createdAt,
            'updated_at'     => $this->updatedAt,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? '',
            name: $data['name'] ?? '',
            slug: $data['slug'] ?? '',
            fields: $data['fields'] ?? [],
            authOverrides: $data['auth_overrides'] ?? [],
            userRole: $data['user_role'] ?? '',
            redirectUrl: $data['redirect_url'] ?? '',
            branding: $data['branding'] ?? [],
            status: $data['status'] ?? 'active',
            description: $data['description'] ?? null,
            createdBy: isset($data['created_by']) ? (int) $data['created_by'] : null,
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null,
        );
    }
}
