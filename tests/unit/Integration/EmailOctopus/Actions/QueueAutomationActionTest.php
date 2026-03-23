<?php

namespace WSms\Tests\Unit\Integration\EmailOctopus\Actions;

use PHPUnit\Framework\TestCase;
use WSms\Integration\EmailOctopus\Actions\QueueAutomationAction;
use WSms\Integration\EmailOctopus\EmailOctopusApiClient;

class QueueAutomationActionTest extends TestCase
{
    private QueueAutomationAction $action;
    private EmailOctopusApiClient $client;

    protected function setUp(): void
    {
        $this->client = $this->createMock(EmailOctopusApiClient::class);
        $this->action = new QueueAutomationAction($this->client);
    }

    public function testExecuteCallsQueueWithTwoArgs(): void
    {
        $this->client->expects($this->once())
            ->method('queueIntoAutomation')
            ->with('auto-123', 'test@example.com')
            ->willReturn([]);

        $result = $this->action->execute([], [
            'automation_id' => 'auto-123',
            'email'         => 'test@example.com',
        ]);

        $this->assertTrue($result->success);
        $this->assertSame('auto-123', $result->output['automation_id']);
        $this->assertSame('test@example.com', $result->output['email']);
    }

    public function testExecuteFailsOnMissingConfig(): void
    {
        $result = $this->action->execute([], ['email' => 'test@example.com']);

        $this->assertFalse($result->success);
    }

    public function testExecuteFailsOnApiError(): void
    {
        $this->client->method('queueIntoAutomation')
            ->willThrowException(new \RuntimeException('Automation not found'));

        $result = $this->action->execute([], [
            'automation_id' => 'auto-bad',
            'email'         => 'test@example.com',
        ]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Automation not found', $result->error);
    }
}
