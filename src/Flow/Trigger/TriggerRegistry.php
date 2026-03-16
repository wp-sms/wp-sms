<?php

namespace WSms\Flow\Trigger;

use WSms\Flow\Contracts\TriggerInterface;

defined('ABSPATH') || exit;

class TriggerRegistry
{
    /** @var array<string, TriggerInterface> */
    private array $triggers = [];

    public function register(TriggerInterface $trigger): void
    {
        $this->triggers[$trigger->getId()] = $trigger;
    }

    public function get(string $id): ?TriggerInterface
    {
        return $this->triggers[$id] ?? null;
    }

    /** @return TriggerInterface[] */
    public function all(): array
    {
        return $this->triggers;
    }

    /** @return array<string, TriggerInterface[]> Grouped by getGroup() */
    public function grouped(): array
    {
        $groups = [];
        foreach ($this->triggers as $trigger) {
            $groups[$trigger->getGroup()][] = $trigger;
        }
        return $groups;
    }
}
