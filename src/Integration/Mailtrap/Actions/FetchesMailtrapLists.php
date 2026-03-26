<?php

namespace WSms\Integration\Mailtrap\Actions;

use WSms\Integration\Mailtrap\MailtrapApiClient;

defined('ABSPATH') || exit;

trait FetchesMailtrapLists
{
    abstract private function getClient(): MailtrapApiClient;

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
