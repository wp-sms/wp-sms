<?php

namespace WSms\Integration\Contracts;

use WSms\Contact\Contracts\ContactRepositoryInterface;

defined('ABSPATH') || exit;

final class ContactImportHelper
{
    /**
     * Match contact by wp_user_id -> email -> phone, or create new.
     * On update: merge data (don't overwrite existing non-null fields with null).
     */
    public static function upsertContact(
        ContactRepositoryInterface $contacts,
        array $data,
        bool $suppressEvents = false,
    ): string {
        $existing = null;

        if (!empty($data['wp_user_id'])) {
            $existing = $contacts->findByWpUser((int) $data['wp_user_id']);
        }

        if (!$existing && !empty($data['email'])) {
            $existing = $contacts->findByEmail($data['email']);
        }

        if (!$existing && !empty($data['phone'])) {
            $existing = $contacts->findByPhone($data['phone']);
        }

        if ($existing) {
            $updateData = array_filter($data, fn($v) => $v !== null && $v !== '');
            unset($updateData['source'], $updateData['source_ref']);
            $contacts->update($existing['id'], $updateData);
            return $existing['id'];
        }

        return $contacts->create($data, $suppressEvents);
    }

    /**
     * Map source fields to contact fields using a mapping config.
     */
    public static function applyFieldMapping(
        array $sourceData,
        array $fieldMapping,
    ): array {
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
