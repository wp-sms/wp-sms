<?php

namespace WSms\Webhook;

use WSms\Dependencies\Symfony\Component\Uid\Ulid;

defined('ABSPATH') || exit;

class WebhookRepository
{
    private const OPTION_KEY = 'wsms_outbound_webhooks';

    /**
     * Upsert a webhook config. Auto-generates ULID for new entries.
     *
     * @return string The webhook ID.
     */
    public function save(array $webhook): string
    {
        $all = $this->loadAll();

        if (empty($webhook['id'])) {
            $webhook['id'] = (string) new Ulid();
        }

        $now = gmdate('Y-m-d\TH:i:s\Z');

        if (!isset($all[$webhook['id']])) {
            $webhook['created_at'] = $webhook['created_at'] ?? $now;
        }

        $webhook['updated_at'] = $now;

        $all[$webhook['id']] = $webhook;
        update_option(self::OPTION_KEY, $all, false);

        return $webhook['id'];
    }

    public function find(string $id): ?array
    {
        $all = $this->loadAll();

        return $all[$id] ?? null;
    }

    /** @return array<string, array> All webhook configs keyed by ID. */
    public function findAll(): array
    {
        return $this->loadAll();
    }

    public function delete(string $id): bool
    {
        $all = $this->loadAll();

        if (!isset($all[$id])) {
            return false;
        }

        unset($all[$id]);
        update_option(self::OPTION_KEY, $all, false);

        return true;
    }

    /**
     * Find all active webhooks subscribed to a given event.
     *
     * @return array[] Matching webhook configs.
     */
    public function findActiveForEvent(string $eventName): array
    {
        $matches = [];

        foreach ($this->loadAll() as $webhook) {
            if (($webhook['status'] ?? 'paused') !== 'active') {
                continue;
            }

            if (in_array($eventName, $webhook['events'] ?? [], true)) {
                $matches[] = $webhook;
            }
        }

        return $matches;
    }

    /** @return array<string, array> */
    private function loadAll(): array
    {
        return get_option(self::OPTION_KEY, []) ?: [];
    }
}
