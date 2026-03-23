<?php

namespace WSms\Integration\EmailOctopus\Actions;

use WSms\Integration\EmailOctopus\EmailOctopusApiClient;

defined('ABSPATH') || exit;

trait FetchesEmailOctopusLists
{
    abstract private function getClient(): EmailOctopusApiClient;

    public function getConfigOptions(string $fieldKey, array $context = []): array
    {
        if ($fieldKey === 'list_id') {
            try {
                $lists = $this->getClient()->getLists();

                return array_map(fn($list) => [
                    'value' => $list['id'],
                    'label' => $list['name'],
                ], $lists);
            } catch (\RuntimeException) {
                return [];
            }
        }

        return [];
    }
}
