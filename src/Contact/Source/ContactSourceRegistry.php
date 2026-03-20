<?php

namespace WSms\Contact\Source;

use WSms\Contact\Source\Contracts\ContactSourceInterface;

defined('ABSPATH') || exit;

class ContactSourceRegistry
{
    /** @var array<string, ContactSourceInterface> */
    private array $sources = [];

    public function register(ContactSourceInterface $source): void
    {
        $this->sources[$source->getType()] = $source;
    }

    public function get(string $type): ?ContactSourceInterface
    {
        return $this->sources[$type] ?? null;
    }

    /** @return ContactSourceInterface[] */
    public function all(): array
    {
        return $this->sources;
    }

    public function has(string $type): bool
    {
        return isset($this->sources[$type]);
    }
}
