<?php

namespace WSms\Tests\Unit\Integration\EmailOctopus\Actions;

use PHPUnit\Framework\TestCase;
use WSms\Integration\EmailOctopus\Actions\RemoveTagAction;
use WSms\Integration\EmailOctopus\EmailOctopusApiClient;

class RemoveTagActionTest extends TestCase
{
    private RemoveTagAction $action;
    private EmailOctopusApiClient $client;

    protected function setUp(): void
    {
        $this->client = $this->createMock(EmailOctopusApiClient::class);
        $this->action = new RemoveTagAction($this->client);
    }

    public function testExecuteCallsUpdateContactWithFalseTag(): void
    {
        $this->client->expects($this->once())
            ->method('updateContact')
            ->with('list-1', 'test@example.com', [], ['old-tag' => false])
            ->willReturn(['id' => 'abc']);

        $result = $this->action->execute([], [
            'list_id'  => 'list-1',
            'email'    => 'test@example.com',
            'tag_name' => 'old-tag',
        ]);

        $this->assertTrue($result->success);
        $this->assertSame('old-tag', $result->output['tag_name']);
    }

    public function testExecuteFailsOnMissingConfig(): void
    {
        $result = $this->action->execute([], ['email' => 'test@example.com']);

        $this->assertFalse($result->success);
    }
}
