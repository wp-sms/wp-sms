<?php

namespace WSms\Tests\Unit\Flow\Contracts;

use PHPUnit\Framework\TestCase;
use WSms\Flow\Contracts\AbstractAction;
use WSms\Flow\Contracts\ActionInterface;
use WSms\Flow\Contracts\ActionResult;

class AbstractActionTest extends TestCase
{
    public function testImplementsActionInterface(): void
    {
        $action = new class extends AbstractAction {
            public function getId(): string { return 'test.action'; }
            public function getName(): string { return 'Test'; }
            public function getGroup(): string { return 'Test'; }
            public function getConfigSchema(): array { return []; }
            public function execute(array $payload, array $config): ActionResult {
                return ActionResult::success();
            }
        };

        $this->assertInstanceOf(ActionInterface::class, $action);
        $this->assertSame('test.action', $action->getId());
    }
}
