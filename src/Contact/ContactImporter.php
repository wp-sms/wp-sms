<?php

namespace WSms\Contact;

use WSms\Dependencies\League\Csv\Reader;
use WSms\Contact\Contracts\ContactRepositoryInterface;

defined('ABSPATH') || exit;

class ContactImporter
{
    public function __construct(
        private readonly ContactRepositoryInterface $contacts,
    ) {
    }

    public function importFromCsv(string $filePath, array $fieldMapping = []): array
    {
        $reader = Reader::createFromPath($filePath, 'r');
        $reader->setHeaderOffset(0);
        // setEscape('') is required for PHP 8.4+ compatibility per League CSV docs.
        $reader->setEscape('');

        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($reader->getRecords() as $offset => $record) {
            try {
                $data = $this->mapFields($record, $fieldMapping);

                if (empty($data['email']) && empty($data['phone'])) {
                    $skipped++;
                    continue;
                }

                // Check for existing contact by email
                if (!empty($data['email'])) {
                    $existing = $this->contacts->findByEmail($data['email']);
                    if ($existing) {
                        $this->contacts->update($existing['id'], $data);
                        $imported++;
                        continue;
                    }
                }

                $data['source'] = 'import';
                $this->contacts->create($data);
                $imported++;
            } catch (\Throwable $e) {
                $errors[] = "Row {$offset}: {$e->getMessage()}";
            }
        }

        return [
            'imported' => $imported,
            'skipped'  => $skipped,
            'errors'   => $errors,
        ];
    }

    private function mapFields(array $record, array $mapping): array
    {
        $defaults = [
            'email'      => 'email',
            'phone'      => 'phone',
            'first_name' => 'first_name',
            'last_name'  => 'last_name',
        ];

        $mapping = array_merge($defaults, $mapping);
        $data = [];

        foreach ($mapping as $field => $csvColumn) {
            if (isset($record[$csvColumn])) {
                $data[$field] = trim($record[$csvColumn]);
            }
        }

        return $data;
    }
}
