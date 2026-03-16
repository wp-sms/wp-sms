<?php

namespace WSms\Integration;

use WSms\Integration\Contracts\IntegrationInterface;

defined('ABSPATH') || exit;

class IntegrationRegistry
{
    /** @var array<string, IntegrationInterface> */
    private array $integrations = [];

    public function register(IntegrationInterface $integration): void
    {
        $this->integrations[$integration->getId()] = $integration;
    }

    public function get(string $id): ?IntegrationInterface
    {
        return $this->integrations[$id] ?? null;
    }

    /** @return IntegrationInterface[] Only integrations where isAvailable() === true */
    public function getAvailable(): array
    {
        return array_filter($this->integrations, fn($i) => $i->isAvailable());
    }

    /** @return IntegrationInterface[] */
    public function getAll(): array
    {
        return $this->integrations;
    }

    /** @return IntegrationInterface[] */
    public function getByCategory(string $category): array
    {
        return array_filter($this->integrations, fn($i) => $i->getCategory() === $category);
    }
}
