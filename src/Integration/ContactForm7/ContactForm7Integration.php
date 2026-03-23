<?php

namespace WSms\Integration\ContactForm7;

use WSms\Integration\ContactForm7\Triggers\FormSubmittedTrigger;
use WSms\Integration\Contracts\IntegrationInterface;

defined('ABSPATH') || exit;

class ContactForm7Integration implements IntegrationInterface
{
    public function getId(): string
    {
        return 'contactform7';
    }

    public function getName(): string
    {
        return 'Contact Form 7';
    }

    public function getDescription(): string
    {
        return 'Trigger flows when Contact Form 7 forms are submitted.';
    }

    public function getCategory(): string
    {
        return 'forms';
    }

    public function getIcon(): string
    {
        return 'file-text';
    }

    public function isAvailable(): bool
    {
        return class_exists('WPCF7');
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
            new FormSubmittedTrigger(),
        ];
    }

    public function getActions(): array
    {
        return [];
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
        return class_exists('WPCF7');
    }
}
