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

        $writer->insertOne(['id', 'email', 'phone', 'first_name', 'last_name', 'status', 'source', 'created_at']);

        $offset = 0;
        $batch = 500;

        do {
            $contacts = $this->contacts->findAll($filters, $batch, $offset);

            foreach ($contacts as $contact) {
                $writer->insertOne([
                    $contact['id'],
                    $contact['email'] ?? '',
                    $contact['phone'] ?? '',
                    $contact['first_name'] ?? '',
                    $contact['last_name'] ?? '',
                    $contact['status'] ?? '',
                    $contact['source'] ?? '',
                    $contact['created_at'] ?? '',
                ]);
            }

            $offset += $batch;
        } while (count($contacts) === $batch);

        return $outputPath;
    }
}
