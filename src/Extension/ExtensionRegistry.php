<?php

namespace WSms\Extension;

defined('ABSPATH') || exit;

class ExtensionRegistry
{
    /** @var array<string, array<string, mixed>> */
    private array $extensions = [];

    /**
     * Register an add-on extension.
     *
     * @param array{id: string, name: string, description?: string, version?: string, type?: string, requires?: string} $extension
     */
    public function register(array $extension): void
    {
        $id = $extension['id'] ?? '';

        if ($id === '') {
            return;
        }

        $this->extensions[$id] = [
            'id'          => $id,
            'name'        => $extension['name'] ?? $id,
            'description' => $extension['description'] ?? '',
            'version'     => $extension['version'] ?? '',
            'type'        => $extension['type'] ?? 'addon',
            'requires'    => $extension['requires'] ?? '',
        ];
    }

    /**
     * Get a registered extension by ID.
     *
     * @return array<string, mixed>|null
     */
    public function get(string $id): ?array
    {
        return $this->extensions[$id] ?? null;
    }

    /**
     * Get all registered extensions.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getAll(): array
    {
        return $this->extensions;
    }
}
