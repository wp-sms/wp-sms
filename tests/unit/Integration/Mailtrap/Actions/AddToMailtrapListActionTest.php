<?php

namespace WSms\Tests\Unit\Integration\Mailtrap\Actions;

use PHPUnit\Framework\TestCase;
use WSms\Integration\Mailtrap\Actions\AddToMailtrapListAction;
use WSms\Integration\Mailtrap\MailtrapApiClient;

class AddToMailtrapListActionTest extends TestCase
{
    private AddToMailtrapListAction $action;
    private MailtrapApiClient $client;

    protected function setUp(): void
    {
        $this->client = $this->createMock(MailtrapApiClient::class);
        $this->action = new AddToMailtrapListAction($this->client);
    }

    public function testExecuteCallsUpsertContact(): void
    {
        $this->client->expects($this->once())
            ->method('upsertContact')
            ->with(42, 'test@example.com', ['first_name' => 'Jane'])
            ->willReturn(['id' => 100, 'email' => 'test@example.com']);

        $result = $this->action->execute([], [
            'list_id'    => '42',
            'email'      => 'test@example.com',
            'first_name' => 'Jane',
        ]);

        $this->assertTrue($result->success);
        $this->assertSame('100', $result->output['contact_id']);
        $this->assertSame('test@example.com', $result->output['email']);
    }

    public function testExecuteHandlesEmptyResultId(): void
    {
        $this->client->method('upsertContact')->willReturn([]);

        $result = $this->action->execute([], [
            'list_id' => '42',
            'email'   => 'test@example.com',
        ]);

        $this->assertTrue($result->success);
        $this->assertSame('', $result->output['contact_id']);
    }

    public function testExecuteFailsOnMissingListId(): void
    {
        $result = $this->action->execute([], ['email' => 'test@example.com']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->error);
    }

    public function testExecuteFailsOnMissingEmail(): void
    {
        $result = $this->action->execute([], ['list_id' => '42']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->error);
    }

    public function testExecuteFailsOnApiError(): void
    {
        $this->client->method('upsertContact')
            ->willThrowException(new \RuntimeException('Mailtrap API error (500): Internal server error'));

        $result = $this->action->execute([], [
            'list_id' => '42',
            'email'   => 'test@example.com',
        ]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Internal server error', $result->error);
    }

    public function testGetIdReturnsCorrectId(): void
    {
        $this->assertSame('mailtrap_add_to_list', $this->action->getId());
    }

    public function testGetGroupReturnsMailtrap(): void
    {
        $this->assertSame('Mailtrap', $this->action->getGroup());
    }
}
