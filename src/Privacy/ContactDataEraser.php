<?php

namespace WSms\Privacy;

use WSms\Contact\ContactRepository;
use WSms\Database\Connection;

defined('ABSPATH') || exit;

class ContactDataEraser
{
    public function __construct(
        private readonly ContactRepository $contacts,
        private readonly Connection $db,
    ) {
    }

    public function erase(string $email, int $page): array
    {
        $contact = $this->contacts->findByEmail($email);

        if (!$contact) {
            return [
                'items_removed'  => false,
                'items_retained' => false,
                'messages'       => [],
                'done'           => true,
            ];
        }

        $contactId = $contact['id'];
        $removed = 0;

        // Delete contact_tag entries.
        $removed += $this->db->delete(Connection::TABLE_CONTACT_TAG, ['contact_id' => $contactId]);

        // Delete verification records for this identifier.
        if (!empty($contact['email'])) {
            $removed += $this->db->delete(Connection::TABLE_VERIFICATIONS, ['identifier' => $contact['email']]);
        }
        if (!empty($contact['phone'])) {
            $removed += $this->db->delete(Connection::TABLE_VERIFICATIONS, ['identifier' => $contact['phone']]);
        }

        // Delete the contact record.
        $removed += $this->db->delete(Connection::TABLE_CONTACTS, ['id' => $contactId]);

        return [
            'items_removed'  => $removed > 0,
            'items_retained' => false,
            'messages'       => [],
            'done'           => true,
        ];
    }
}
