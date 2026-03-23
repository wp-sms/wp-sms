<?php

namespace WSms\Tests\Unit\Integration\EmailOctopus\Actions;

use PHPUnit\Framework\TestCase;
use WSms\Integration\EmailOctopus\Actions\AddToListAction;
use WSms\Integration\EmailOctopus\EmailOctopusApiClient;

class AddToListActionTest extends TestCase
{
    private AddToListAction $action;
    private EmailOctopusApiClient $client;

    protected function setUp(): void
    {
        $this->client = $this->createMock(EmailOctopusApiClient::class);
        $this->action = new AddToListAction($this->client);
    }

    public function testExecuteCallsUpsertContact(): void
    {
        $this->client->expects($this->once())
            ->method('upsertContact')
            ->with('list-1', 'test@example.com', ['FirstName' => 'Jane'])
            ->willReturn(['id' => 'contact-uuid']);

        $result = $this->action->execute([], [
            'list_id'    => 'list-1',
            'email'      => 'test@example.com',
            'first_name' => 'Jane',
        ]);

        $this->assertTrue($result->success);
        $this->assertSame('contact-uuid', $result->output['contact_id']);
        $this->assertSame('test@example.com', $result->output['email']);
    }

    public function testExecuteFallsBackToMd5ContactId(): void
    {
        $this->client->method('upsertContact')->willReturn([]);

        $result = $this->action->execute([], [
            'list_id' => 'list-1',
            'email'   => 'test@example.com',
        ]);

        $this->assertTrue($result->success);
        $this->assertSame(md5('test@example.com'), $result->output['contact_id']);
    }

    public function testExecuteFailsOnMissingConfig(): void
    {
        $result = $this->action->execute([], ['email' => 'test@example.com']);

        $this->assertFalse($result->success);
    }

    public function testExecuteFailsOnApiError(): void
    {
        $this->client->method('upsertContact')
            ->willThrowException(new \RuntimeException('Conflict'));

        $result = $this->action->execute([], [
            'list_id' => 'list-1',
            'email'   => 'test@example.com',
        ]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Conflict', $result->error);
    }
}
