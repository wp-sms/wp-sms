<?php

namespace WSms\Tests\Unit\Integration\Contact\Actions;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Contact\Contracts\ContactRepositoryInterface;
use WSms\Contact\Contracts\ListRepositoryInterface;
use WSms\Integration\Contact\Actions\UnsubscribeFromListAction;

class UnsubscribeFromListActionTest extends TestCase
{
    private UnsubscribeFromListAction $action;
    private MockObject&ContactRepositoryInterface $contactRepository;
    private MockObject&ListRepositoryInterface $listRepository;

    protected function setUp(): void
    {
        $this->contactRepository = $this->createMock(ContactRepositoryInterface::class);
        $this->listRepository = $this->createMock(ListRepositoryInterface::class);

        $this->action = new UnsubscribeFromListAction(
            $this->contactRepository,
            $this->listRepository,
        );
    }

    public function testMetadata(): void
    {
        $this->assertSame('unsubscribe_from_list', $this->action->getId());
        $this->assertSame('Unsubscribe from List', $this->action->getName());
        $this->assertSame('Contacts', $this->action->getGroup());
    }

    public function testConfigSchemaHasExpectedFields(): void
    {
        $schema = $this->action->getConfigSchema();
        $this->assertArrayHasKey('contact_id', $schema);
        $this->assertArrayHasKey('list_id', $schema);
        $this->assertTrue($schema['list_id']['required']);
        $this->assertTrue($schema['list_id']['dynamic']);
    }

    public function testGetConfigOptionsReturnsStaticListsOnly(): void
    {
        $this->listRepository->expects($this->once())
            ->method('findAll')
            ->with('static')
            ->willReturn([
                ['id' => 'l1', 'name' => 'Subscribers'],
            ]);

        $options = $this->action->getConfigOptions('list_id');
        $this->assertCount(1, $options);
        $this->assertSame('l1', $options[0]['value']);
    }

    public function testExecuteHappyPath(): void
    {
        $this->listRepository->method('find')
            ->with('list-1')
            ->willReturn(['id' => 'list-1', 'name' => 'Newsletter', 'tag_id' => 'tag-news']);

        $this->contactRepository->method('find')
            ->with('contact-1')
            ->willReturn(['id' => 'contact-1']);

        $this->contactRepository->expects($this->once())
            ->method('removeTag')
            ->with('contact-1', 'tag-news');

        $result = $this->action->execute(
            ['contact_id' => 'contact-1'],
            ['list_id' => 'list-1'],
        );

        $this->assertTrue($result->success);
        $this->assertSame('contact-1', $result->output['contact_id']);
        $this->assertSame('list-1', $result->output['list_id']);
        $this->assertSame('Newsletter', $result->output['list_name']);
    }

    public function testExecuteNotSubscribedSucceeds(): void
    {
        $this->listRepository->method('find')
            ->willReturn(['id' => 'list-1', 'name' => 'Newsletter', 'tag_id' => 'tag-news']);

        $this->contactRepository->method('find')
            ->willReturn(['id' => 'contact-1']);

        // removeTag is a DELETE — idempotent, no error if tag not present
        $this->contactRepository->expects($this->once())->method('removeTag');

        $result = $this->action->execute(
            ['contact_id' => 'contact-1'],
            ['list_id' => 'list-1'],
        );

        $this->assertTrue($result->success);
    }

    public function testExecuteContactNotFound(): void
    {
        $result = $this->action->execute(
            [],
            ['list_id' => 'list-1'],
        );

        $this->assertFalse($result->success);
        $this->assertSame('Contact not found', $result->error);
    }

    public function testExecuteListNotFound(): void
    {
        $this->listRepository->method('find')->willReturn(null);

        $result = $this->action->execute(
            ['contact_id' => 'contact-1'],
            ['list_id' => 'list-missing'],
        );

        $this->assertFalse($result->success);
        $this->assertSame('List not found', $result->error);
    }

    public function testExecuteListHasNoTagId(): void
    {
        $this->listRepository->method('find')
            ->willReturn(['id' => 'list-1', 'name' => 'Bad', 'tag_id' => '']);

        $result = $this->action->execute(
            ['contact_id' => 'contact-1'],
            ['list_id' => 'list-1'],
        );

        $this->assertFalse($result->success);
        $this->assertSame('List has no associated tag', $result->error);
    }

    public function testExecuteEmptyListId(): void
    {
        $result = $this->action->execute(
            ['contact_id' => 'contact-1'],
            ['list_id' => ''],
        );

        $this->assertFalse($result->success);
        $this->assertSame('List ID is required', $result->error);
    }
}
