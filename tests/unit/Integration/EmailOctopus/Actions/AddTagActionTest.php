<?php

namespace WSms\Tests\Unit\Integration\EmailOctopus\Actions;

use PHPUnit\Framework\TestCase;
use WSms\Integration\EmailOctopus\Actions\AddTagAction;
use WSms\Integration\EmailOctopus\EmailOctopusApiClient;

class AddTagActionTest extends TestCase
{
    private AddTagAction $action;
    private EmailOctopusApiClient $client;

    protected function setUp(): void
    {
        $this->client = $this->createMock(EmailOctopusApiClient::class);
        $this->action = new AddTagAction($this->client);
    }

    public function testMetadata(): void
    {
        $this->assertSame('emailoctopus_add_tag', $this->action->getId());
        $this->assertSame('EmailOctopus', $this->action->getGroup());
    }

    public function testExecuteCallsUpdateContactWithTrueTag(): void
    {
        $this->client->expects($this->once())
            ->method('updateContact')
            ->with('list-1', 'test@example.com', [], ['vip' => true])
            ->willReturn(['id' => 'abc']);

        $result = $this->action->execute([], [
            'list_id'  => 'list-1',
            'email'    => 'test@example.com',
            'tag_name' => 'vip',
        ]);

        $this->assertTrue($result->success);
        $this->assertSame('test@example.com', $result->output['email']);
        $this->assertSame('vip', $result->output['tag_name']);
    }

    public function testExecuteFailsOnMissingConfig(): void
    {
        $result = $this->action->execute([], ['list_id' => 'list-1', 'email' => 'test@example.com']);

        $this->assertFalse($result->success);
    }

    public function testExecuteFailsOnApiError(): void
    {
        $this->client->method('updateContact')
            ->willThrowException(new \RuntimeException('API error'));

        $result = $this->action->execute([], [
            'list_id'  => 'list-1',
            'email'    => 'test@example.com',
            'tag_name' => 'vip',
        ]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('API error', $result->error);
    }
}
