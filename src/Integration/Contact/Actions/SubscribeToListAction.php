<?php

namespace WSms\Integration\Contact\Actions;

defined('ABSPATH') || exit;

class SubscribeToListAction extends AbstractListAction
{
    public function getId(): string
    {
        return 'subscribe_to_list';
    }

    public function getName(): string
    {
        return __('Subscribe to List', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Subscribe a contact to a static list', 'wp-sms');
    }

    protected function applyTagChange(string $contactId, string $tagId): void
    {
        $this->contactRepository->addTag($contactId, $tagId);
    }

    protected function getContactIdDescription(): string
    {
        return __('The contact to subscribe. Use {{contact_id}} from trigger data, or leave empty to resolve from user_id.', 'wp-sms');
    }

    protected function getListIdDescription(): string
    {
        return __('The list to subscribe to', 'wp-sms');
    }
}
