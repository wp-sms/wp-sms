<?php

namespace WSms\Tests\Unit\Integration\Mailtrap\Actions;

use PHPUnit\Framework\TestCase;
use WSms\Integration\Mailtrap\Actions\RemoveFromMailtrapListAction;
use WSms\Integration\Mailtrap\MailtrapApiClient;

class RemoveFromMailtrapListActionTest extends TestCase
{
    private RemoveFromMailtrapListAction $action;
    private MailtrapApiClient $client;

    protected function setUp(): void
    {
        $this->client = $this->createMock(MailtrapApiClient::class);
        $this->action = new RemoveFromMailtrapListAction($this->client);
    }

    public function testExecuteDeletesContact(): void
    {
        $this->client->method('findContactByEmail')
            ->willReturn(['id' => 100, 'email' => 'test@example.com']);

        $this->client->expects($this->once())
            ->method('deleteContact')
            ->with(100);

        $result = $this->action->execute([], [
            'list_id' => '42',
            'email'   => 'test@example.com',
        ]);

        $this->assertTrue($result->success);
        $this->assertSame('test@example.com', $result->output['email']);
    }

    public function testExecuteSucceedsWhenContactNotFound(): void
    {
        $this->client->method('findContactByEmail')
            ->willReturn(null);

        $result = $this->action->execute([], [
            'list_id' => '42',
            'email'   => 'missing@example.com',
        ]);

        $this->assertTrue($result->success);
        $this->assertSame('missing@example.com', $result->output['email']);
    }

    public function testExecuteFailsOnMissingConfig(): void
    {
        $result = $this->action->execute([], ['email' => 'test@example.com']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->error);
    }

    public function testExecuteHandles404Gracefully(): void
    {
        $this->client->method('findContactByEmail')
            ->willThrowException(new \RuntimeException('Mailtrap API error (404): Not found'));

        $result = $this->action->execute([], [
            'list_id' => '42',
            'email'   => 'test@example.com',
        ]);

        $this->assertTrue($result->success);
        $this->assertSame('test@example.com', $result->output['email']);
    }

    public function testExecuteFailsOnApiError(): void
    {
        $this->client->method('findContactByEmail')
            ->willThrowException(new \RuntimeException('Mailtrap API error (500): Server error'));

        $result = $this->action->execute([], [
            'list_id' => '42',
            'email'   => 'test@example.com',
        ]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Server error', $result->error);
    }

    public function testGetIdReturnsCorrectId(): void
    {
        $this->assertSame('mailtrap_remove_from_list', $this->action->getId());
    }
}
