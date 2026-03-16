<?php

namespace WSms\Flow\Action;

use WSms\Flow\Contracts\ActionInterface;

defined('ABSPATH') || exit;

class ActionRegistry
{
    /** @var array<string, ActionInterface> */
    private array $actions = [];

    public function register(ActionInterface $action): void
    {
        $this->actions[$action->getId()] = $action;
    }

    public function get(string $id): ?ActionInterface
    {
        return $this->actions[$id] ?? null;
    }

    /** @return ActionInterface[] */
    public function all(): array
    {
        return $this->actions;
    }

    /** @return array<string, ActionInterface[]> Grouped by getGroup() */
    public function grouped(): array
    {
        $groups = [];
        foreach ($this->actions as $action) {
            $groups[$action->getGroup()][] = $action;
        }
        return $groups;
    }
}
