<?php

namespace WSms\Contact\Source;

use WSms\Contact\Contracts\ContactRepositoryInterface;
use WSms\Contact\Source\Contracts\ContactSourceInterface;

defined('ABSPATH') || exit;

abstract class AbstractContactSource implements ContactSourceInterface
{
    public function __construct(
        protected readonly ContactRepositoryInterface $contacts,
    ) {
    }

    /**
     * Upsert a contact: match by wp_user_id → email → phone → create new.
     * On update: merge data (don't overwrite existing non-null fields with null).
     */
    protected function upsertContact(array $data, bool $suppressEvents = false): string
    {
        $existing = null;

        // 1. Match by wp_user_id
        if (!empty($data['wp_user_id'])) {
            $existing = $this->contacts->findByWpUser((int) $data['wp_user_id']);
        }

        // 2. Match by email
        if (!$existing && !empty($data['email'])) {
            $existing = $this->contacts->findByEmail($data['email']);
        }

        // 3. Match by phone
        if (!$existing && !empty($data['phone'])) {
            $existing = $this->contacts->findByPhone($data['phone']);
        }

        if ($existing) {
            // Merge: only overwrite with non-null values
            $updateData = array_filter($data, fn($v) => $v !== null && $v !== '');
            // Preserve original provenance — source/source_ref record the creation context
            unset($updateData['source'], $updateData['source_ref']);
            $this->contacts->update($existing['id'], $updateData);
            return $existing['id'];
        }

        return $this->contacts->create($data, $suppressEvents);
    }

    /**
     * Map source fields → contact fields using configured mapping.
     */
    protected function applyFieldMapping(array $sourceData, array $fieldMapping): array
    {
        $mapped = [];

        foreach ($fieldMapping as $contactField => $sourceField) {
            if ($sourceField === '' || $sourceField === null) {
                continue;
            }
            $value = $sourceData[$sourceField] ?? null;
            if ($value !== null && $value !== '') {
                $mapped[$contactField] = $value;
            }
        }

        return $mapped;
    }
}
