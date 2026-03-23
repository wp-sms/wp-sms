<?php

namespace WSms\SubscriptionForm;

use WSms\Dependencies\Symfony\Component\Uid\Ulid;

defined('ABSPATH') || exit;

class SubscriptionForm
{
    public const VALID_FIELD_KEYS = ['email', 'phone', 'first_name', 'last_name'];

    public function __construct(
        private string $id,
        private string $name,
        private string $slug,
        private string $status = 'active',
        private array $fields = [],
        private ?string $listId = null,
        private ?string $tagId = null,
        private bool $doubleOptin = false,
        private string $optinChannel = 'email',
        private array $appearance = [],
        private string $successMessage = '',
        private ?string $redirectUrl = null,
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

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @return string[]
     */
    public function getFieldKeys(): array
    {
        return array_column($this->fields, 'key');
    }

    public function hasField(string $key): bool
    {
        return in_array($key, $this->getFieldKeys(), true);
    }

    public function isFieldRequired(string $key): bool
    {
        foreach ($this->fields as $field) {
            if ($field['key'] === $key) {
                return !empty($field['required']);
            }
        }

        return false;
    }

    public function getListId(): ?string
    {
        return $this->listId;
    }

    public function getTagId(): ?string
    {
        return $this->tagId;
    }

    public function requiresDoubleOptIn(): bool
    {
        return $this->doubleOptin;
    }

    public function getOptInChannel(): string
    {
        return $this->optinChannel;
    }

    public function getAppearance(): array
    {
        return $this->appearance;
    }

    public function getButtonText(): string
    {
        return $this->appearance['button_text'] ?? __('Subscribe', 'wp-sms');
    }

    public function getSuccessMessage(): string
    {
        return $this->successMessage ?: __('Thanks for subscribing!', 'wp-sms');
    }

    public function getRedirectUrl(): ?string
    {
        return $this->redirectUrl;
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

    public function toArray(): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'slug'            => $this->slug,
            'status'          => $this->status,
            'fields'          => $this->fields,
            'list_id'         => $this->listId,
            'tag_id'          => $this->tagId,
            'double_optin'    => $this->doubleOptin,
            'optin_channel'   => $this->optinChannel,
            'appearance'      => $this->appearance,
            'success_message' => $this->successMessage,
            'redirect_url'    => $this->redirectUrl,
            'created_by'      => $this->createdBy,
            'created_at'      => $this->createdAt,
            'updated_at'      => $this->updatedAt,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? '',
            name: $data['name'] ?? '',
            slug: $data['slug'] ?? '',
            status: $data['status'] ?? 'active',
            fields: $data['fields'] ?? [],
            listId: $data['list_id'] ?? null,
            tagId: $data['tag_id'] ?? null,
            doubleOptin: !empty($data['double_optin']),
            optinChannel: $data['optin_channel'] ?? 'email',
            appearance: $data['appearance'] ?? [],
            successMessage: $data['success_message'] ?? '',
            redirectUrl: $data['redirect_url'] ?? null,
            createdBy: isset($data['created_by']) ? (int) $data['created_by'] : null,
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null,
        );
    }
}
