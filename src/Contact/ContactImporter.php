<?php

namespace WSms\Contact;

use WSms\Dependencies\League\Csv\Reader;
use WSms\Contact\Contracts\ContactRepositoryInterface;
use WSms\Database\Connection;

defined('ABSPATH') || exit;

class ContactImporter
{
    public function __construct(
        private readonly ContactRepositoryInterface $contacts,
        private readonly Connection $db,
    ) {
    }

    /**
     * @param array{match_field?: string, duplicate_handling?: string} $options
     */
    public function importFromCsv(string $filePath, array $fieldMapping = [], array $options = []): array
    {
        $reader = self::openCsv($filePath);

        $matchField = $options['match_field'] ?? 'email';
        $duplicateHandling = $options['duplicate_handling'] ?? 'update';

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        $this->db->query('START TRANSACTION');

        try {
            foreach ($reader->getRecords() as $offset => $record) {
                try {
                    $data = $this->mapFields($record, $fieldMapping);

                    if (empty($data['email']) && empty($data['phone'])) {
                        $skipped++;
                        continue;
                    }

                    $existing = $this->findExisting($data, $matchField);

                    if ($existing) {
                        if ($duplicateHandling === 'skip') {
                            $skipped++;
                            continue;
                        }

                        if ($duplicateHandling === 'update_if_empty') {
                            $updateData = [];
                            foreach ($data as $key => $value) {
                                if ($value !== '' && $value !== null && empty($existing[$key])) {
                                    $updateData[$key] = $value;
                                }
                            }
                            if (!empty($updateData)) {
                                $this->contacts->update($existing['id'], $updateData);
                            }
                        } else {
                            // 'update' — overwrite all fields
                            $this->contacts->update($existing['id'], $data);
                        }
                        $updated++;
                        continue;
                    }

                    $data['source'] = 'import';
                    $data['source_ref'] = $options['source_ref'] ?? null;
                    $this->contacts->create($data);
                    $imported++;
                } catch (\Throwable $e) {
                    $errors[] = "Row {$offset}: {$e->getMessage()}";
                }
            }

            $this->db->query('COMMIT');
        } catch (\Throwable $e) {
            $this->db->query('ROLLBACK');
            throw $e;
        }

        return [
            'imported' => $imported,
            'updated'  => $updated,
            'skipped'  => $skipped,
            'errors'   => $errors,
        ];
    }

    public function previewCsv(string $filePath): array
    {
        $reader = self::openCsv($filePath);

        $headers = $reader->getHeader();
        $rows = [];
        $count = 0;

        foreach ($reader->getRecords() as $record) {
            $rows[] = array_values($record);
            if (++$count >= 5) {
                break;
            }
        }

        return [
            'headers' => $headers,
            'rows'    => $rows,
        ];
    }

    private function findExisting(array $data, string $matchField): ?array
    {
        if ($matchField === 'email' && !empty($data['email'])) {
            return $this->contacts->findByEmail($data['email']);
        }

        if ($matchField === 'phone' && !empty($data['phone'])) {
            return $this->contacts->findByPhone($data['phone']);
        }

        if ($matchField === 'email_or_phone') {
            if (!empty($data['email'])) {
                $found = $this->contacts->findByEmail($data['email']);
                if ($found) {
                    return $found;
                }
            }
            if (!empty($data['phone'])) {
                return $this->contacts->findByPhone($data['phone']);
            }
        }

        return null;
    }

    private static function openCsv(string $filePath): Reader
    {
        $reader = Reader::createFromPath($filePath, 'r');
        $reader->setHeaderOffset(0);
        // setEscape('') is required for PHP 8.4+ compatibility per League CSV docs.
        $reader->setEscape('');
        return $reader;
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
        $customFields = [];

        foreach ($mapping as $field => $csvColumn) {
            if ($csvColumn === '' || !isset($record[$csvColumn])) {
                continue;
            }

            $value = trim($record[$csvColumn]);

            if (str_starts_with($field, 'custom.')) {
                $customFields[substr($field, 7)] = $value;
            } else {
                $data[$field] = $value;
            }
        }

        if (!empty($customFields)) {
            $data['custom_fields'] = $customFields;
        }

        return $data;
    }
}
