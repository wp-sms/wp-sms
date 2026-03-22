<?php

namespace WSms\Integration\Contact\Actions;

defined('ABSPATH') || exit;

class UnsubscribeFromListAction extends AbstractListAction
{
    public function getId(): string
    {
        return 'unsubscribe_from_list';
    }

    public function getName(): string
    {
        return __('Unsubscribe from List', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Unsubscribe a contact from a static list', 'wp-sms');
    }

    protected function applyTagChange(string $contactId, string $tagId): void
    {
        $this->contactRepository->removeTag($contactId, $tagId);
    }

    protected function getContactIdDescription(): string
    {
        return __('The contact to unsubscribe. Use {{contact_id}} from trigger data, or leave empty to resolve from user_id.', 'wp-sms');
    }

    protected function getListIdDescription(): string
    {
        return __('The list to unsubscribe from', 'wp-sms');
    }
}
