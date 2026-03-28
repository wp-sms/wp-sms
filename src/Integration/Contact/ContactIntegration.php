<?php

namespace WSms\Integration\Contact;

use WSms\Contact\Contracts\ContactRepositoryInterface;
use WSms\Contact\Contracts\ListRepositoryInterface;
use WSms\Contact\Contracts\TagRepositoryInterface;
use WSms\Flow\Contracts\FlowRepositoryInterface;
use WSms\Flow\Engine\FlowRunner;
use WSms\Integration\Contact\Actions\AddTagAction;
use WSms\Integration\Contact\Actions\CreateContactAction;
use WSms\Integration\Contact\Actions\RemoveTagAction;
use WSms\Integration\Contact\Actions\SubscribeToListAction;
use WSms\Integration\Contact\Actions\TriggerFlowAction;
use WSms\Integration\Contact\Actions\UnsubscribeFromListAction;
use WSms\Integration\Contact\Actions\UpdateContactAction;
use WSms\Integration\Contact\Actions\UpdateContactStatusAction;
use WSms\Integration\Contact\Triggers\ContactCreatedTrigger;
use WSms\Integration\Contact\Triggers\ContactStatusChangedTrigger;
use WSms\Integration\Contact\Triggers\ContactTaggedTrigger;
use WSms\Integration\Contracts\IntegrationInterface;

defined('ABSPATH') || exit;

class ContactIntegration implements IntegrationInterface
{
    public function __construct(
        private readonly ContactRepositoryInterface $contactRepository,
        private readonly TagRepositoryInterface $tagRepository,
        private readonly ListRepositoryInterface $listRepository,
        private readonly FlowRepositoryInterface $flowRepository,
        private readonly FlowRunner $flowRunner,
    ) {
    }

    public function getId(): string
    {
        return 'wsms_contacts';
    }

    public function getName(): string
    {
        return 'WSMS Contacts';
    }

    public function getDescription(): string
    {
        return __('Contact management triggers and actions for automation flows.', 'wp-sms');
    }

    public function getCategory(): string
    {
        return 'contacts';
    }

    public function getIcon(): string
    {
        return 'users';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function getAuthType(): string
    {
        return 'none';
    }

    public function getAuthSchema(): array
    {
        return [];
    }

    public function getTriggers(): array
    {
        return [
            new ContactCreatedTrigger(),
            new ContactTaggedTrigger($this->tagRepository),
            new ContactStatusChangedTrigger(),
        ];
    }

    public function getActions(): array
    {
        return [
            new AddTagAction($this->contactRepository, $this->tagRepository),
            new RemoveTagAction($this->contactRepository, $this->tagRepository),
            new UpdateContactStatusAction($this->contactRepository),
            new CreateContactAction($this->contactRepository),
            new UpdateContactAction($this->contactRepository),
            new TriggerFlowAction($this->flowRepository, $this->flowRunner),
            new SubscribeToListAction($this->contactRepository, $this->listRepository),
            new UnsubscribeFromListAction($this->contactRepository, $this->listRepository),
        ];
    }

    public function getCapabilities(): array
    {
        return [];
    }

    public function boot(): void
    {
    }

    public function connect(array $credentials): array
    {
        return $credentials;
    }

    public function disconnect(): void
    {
    }

    public function isConnected(): bool
    {
        return true;
    }
}
