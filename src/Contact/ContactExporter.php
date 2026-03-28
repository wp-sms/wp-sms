<?php

namespace WSms\Contact;

use WSms\Dependencies\League\Csv\Writer;
use WSms\Contact\Contracts\ContactRepositoryInterface;

defined('ABSPATH') || exit;

class ContactExporter
{
    public function __construct(
        private readonly ContactRepositoryInterface $contacts,
    ) {
    }

    public function exportToCsv(array $filters = [], string $outputPath = 'php://output'): string
    {
        $writer = Writer::createFromPath($outputPath, 'w+');
        $writer->setEscape('');

        $batch = 500;

        // Pass 1: discover custom field keys (don't store rows)
        $customFieldKeys = [];
        $offset = 0;

        do {
            $contacts = $this->contacts->findAll($filters, $batch, $offset);
            foreach ($contacts as $contact) {
                foreach (array_keys(self::customFields($contact)) as $key) {
                    $customFieldKeys[$key] = true;
                }
            }
            $offset += $batch;
        } while (count($contacts) === $batch);

        $customFieldKeys = array_keys($customFieldKeys);
        sort($customFieldKeys);

        // Build header
        $header = ['id', 'email', 'phone', 'first_name', 'last_name', 'status', 'channel_opt_outs', 'source', 'source_ref', 'wp_user_id', 'tags', 'created_at', 'updated_at'];
        foreach ($customFieldKeys as $key) {
            $header[] = 'custom_' . $key;
        }
        $writer->insertOne($header);

        // Pass 2: stream rows in batches
        $offset = 0;

        do {
            $contacts = $this->contacts->findAll($filters, $batch, $offset);
            foreach ($contacts as $contact) {
                $tags = $this->contacts->getTags($contact['id']);
                $tagNames = implode(', ', array_column($tags, 'name'));
                $cf = self::customFields($contact);

                $row = [
                    $contact['id'],
                    $contact['email'] ?? '',
                    $contact['phone'] ?? '',
                    $contact['first_name'] ?? '',
                    $contact['last_name'] ?? '',
                    $contact['status'] ?? '',
                    self::serializeOptOuts($contact['channel_opt_outs'] ?? null),
                    $contact['source'] ?? '',
                    $contact['source_ref'] ?? '',
                    $contact['wp_user_id'] ?? '',
                    $tagNames,
                    $contact['created_at'] ?? '',
                    $contact['updated_at'] ?? '',
                ];

                foreach ($customFieldKeys as $key) {
                    $row[] = $cf[$key] ?? '';
                }

                $writer->insertOne($row);
            }
            $offset += $batch;
        } while (count($contacts) === $batch);

        return $outputPath;
    }

    private static function serializeOptOuts(mixed $optOuts): string
    {
        if (is_string($optOuts)) {
            $optOuts = json_decode($optOuts, true);
        }

        return is_array($optOuts) && !empty($optOuts) ? json_encode($optOuts) : '';
    }

    private static function customFields(array $contact): array
    {
        $cf = $contact['custom_fields'] ?? [];
        return is_string($cf) ? (json_decode($cf, true) ?? []) : $cf;
    }
}
