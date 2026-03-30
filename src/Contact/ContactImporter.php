<?php

namespace WSms\Contact;

use WSms\Dependencies\League\Csv\Reader;
use WSms\Contact\ContactRepository;
use WSms\Contact\Contracts\ContactRepositoryInterface;
use WSms\Database\Connection;
use WSms\Support\PhoneValidator;

defined('ABSPATH') || exit;

class ContactImporter
{
    public function __construct(
        private readonly ContactRepositoryInterface $contacts,
        private readonly Connection $db,
    ) {
    }

    private const BATCH_SIZE = 500;

    /**
     * @param array{match_field?: string, duplicate_handling?: string} $options
     */
    public function importFromCsv(string $filePath, array $fieldMapping = [], array $options = []): array
    {
        $reader = self::openCsv($filePath);

        $matchField = $options['match_field'] ?? 'email';
        $duplicateHandling = $options['duplicate_handling'] ?? 'update';

        $totals = ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
        $chunk = [];

        foreach ($reader->getRecords() as $offset => $record) {
            $chunk[] = ['offset' => $offset, 'record' => $record];

            if (count($chunk) >= self::BATCH_SIZE) {
                $this->mergeResult($totals, $this->importChunk($chunk, $fieldMapping, $matchField, $duplicateHandling, $options));
                $chunk = [];
            }
        }

        if (!empty($chunk)) {
            $this->mergeResult($totals, $this->importChunk($chunk, $fieldMapping, $matchField, $duplicateHandling, $options));
        }

        return $totals;
    }

    private function importChunk(array $chunk, array $fieldMapping, string $matchField, string $duplicateHandling, array $options): array
    {
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        // Map all rows first
        $mapped = [];
        foreach ($chunk as $item) {
            $data = $this->mapFields($item['record'], $fieldMapping);
            $mapped[] = ['offset' => $item['offset'], 'data' => $data];
        }

        // Batch-fetch existing contacts
        $existingMap = $this->batchFindExisting($mapped, $matchField);

        $this->db->query('START TRANSACTION');

        try {
            foreach ($mapped as $item) {
                try {
                    $data = $item['data'];
                    $offset = $item['offset'];

                    if (empty($data['email']) && empty($data['phone'])) {
                        $skipped++;
                        continue;
                    }

                    // Validate phone E.164 format if present.
                    if (!empty($data['phone']) && !PhoneValidator::isE164($data['phone'])) {
                        $errors[] = sprintf(
                            __('Row %d: Invalid phone number "%s". Must include country code (e.g. +12025551234).', 'wp-sms'),
                            $offset,
                            $data['phone'],
                        );
                        $skipped++;
                        continue;
                    }

                    $existing = $this->lookupExisting($data, $matchField, $existingMap);

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

        return ['imported' => $imported, 'updated' => $updated, 'skipped' => $skipped, 'errors' => $errors];
    }

    private function mergeResult(array &$totals, array $result): void
    {
        $totals['imported'] += $result['imported'];
        $totals['updated'] += $result['updated'];
        $totals['skipped'] += $result['skipped'];
        array_push($totals['errors'], ...$result['errors']);
    }

    private function batchFindExisting(array $mapped, string $matchField): array
    {
        $emails = [];
        $phones = [];

        foreach ($mapped as $item) {
            $data = $item['data'];
            if (in_array($matchField, ['email', 'email_or_phone'], true) && !empty($data['email'])) {
                $emails[] = $data['email'];
            }
            if (in_array($matchField, ['phone', 'email_or_phone'], true) && !empty($data['phone'])) {
                $phones[] = $data['phone'];
            }
        }

        $byEmail = !empty($emails) ? $this->contacts->findByEmails($emails) : [];
        $byPhone = !empty($phones) ? $this->contacts->findByPhones($phones) : [];

        return ['email' => $byEmail, 'phone' => $byPhone];
    }

    private function lookupExisting(array $data, string $matchField, array $existingMap): ?array
    {
        if ($matchField === 'email' && !empty($data['email'])) {
            return $existingMap['email'][strtolower($data['email'])] ?? null;
        }

        if ($matchField === 'phone' && !empty($data['phone'])) {
            $normalized = ContactRepository::normalizePhone($data['phone']);
            return $existingMap['phone'][$normalized] ?? null;
        }

        if ($matchField === 'email_or_phone') {
            if (!empty($data['email'])) {
                $found = $existingMap['email'][strtolower($data['email'])] ?? null;
                if ($found) {
                    return $found;
                }
            }
            if (!empty($data['phone'])) {
                $normalized = ContactRepository::normalizePhone($data['phone']);
                return $existingMap['phone'][$normalized] ?? null;
            }
        }

        return null;
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

            if ($field === 'channel_opt_outs') {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    $data['channel_opt_outs'] = $decoded;
                }
                continue;
            }

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
