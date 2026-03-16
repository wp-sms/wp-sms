<?php

namespace WSms\Tests\Unit\Flow\Contracts;

use PHPUnit\Framework\TestCase;
use WSms\Flow\Contracts\Flow;

class FlowTest extends TestCase
{
    public function testFromArrayAndToArray(): void
    {
        $data = [
            'id'              => '01ABCDEF123456789012345678',
            'name'            => 'Test Flow',
            'trigger_type'    => 'wordpress.user_register',
            'trigger_config'  => '{}',
            'steps'           => '[{"id":"step_1","type":"action","action":"send_message","config":{}}]',
            'status'          => 'active',
            'published_steps' => '[{"id":"step_1","type":"action","action":"send_message","config":{}}]',
            'published_at'    => '2026-01-01 00:00:00',
            'description'     => 'A test flow',
            'priority'        => 5,
            'created_by'      => 1,
        ];

        $flow = Flow::fromArray($data);

        $this->assertSame('01ABCDEF123456789012345678', $flow->getId());
        $this->assertSame('Test Flow', $flow->getName());
        $this->assertSame('wordpress.user_register', $flow->getTriggerType());
        $this->assertSame('active', $flow->getStatus());
        $this->assertSame(5, $flow->getPriority());
        $this->assertSame(1, $flow->getCreatedBy());
        $this->assertNotNull($flow->getPublishedSteps());
        $this->assertNotNull($flow->getPublishedAt());
    }

    public function testActiveStepsReturnsPublishedWhenAvailable(): void
    {
        $flow = new Flow(
            id: 'test',
            name: 'Test',
            triggerType: 'test',
            triggerConfig: [],
            steps: [['id' => 'draft_step']],
            publishedSteps: [['id' => 'published_step']],
        );

        $this->assertSame('published_step', $flow->getActiveSteps()[0]['id']);
    }

    public function testActiveStepsFallsToDraftWhenNoPublished(): void
    {
        $flow = new Flow(
            id: 'test',
            name: 'Test',
            triggerType: 'test',
            triggerConfig: [],
            steps: [['id' => 'draft_step']],
        );

        $this->assertSame('draft_step', $flow->getActiveSteps()[0]['id']);
    }

    public function testAutoGeneratesUlidWhenEmptyId(): void
    {
        $flow = new Flow(
            id: '',
            name: 'Auto ID',
            triggerType: 'test',
            triggerConfig: [],
            steps: [],
        );

        $this->assertNotEmpty($flow->getId());
        $this->assertSame(26, strlen($flow->getId()));
    }
}
