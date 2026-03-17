<?php

namespace WSms\Tests\Unit\Flow\Contracts;

use PHPUnit\Framework\TestCase;
use WSms\Flow\Contracts\AbstractTrigger;
use WSms\Flow\Contracts\TriggerInterface;

class AbstractTriggerTest extends TestCase
{
    public function testImplementsTriggerInterface(): void
    {
        $trigger = new class extends AbstractTrigger {
            public function getId(): string { return 'test.trigger'; }
            public function getName(): string { return 'Test'; }
            public function getGroup(): string { return 'Test'; }
            public function getPayloadSchema(): array { return []; }
            public function subscribe(callable $callback): void {}
        };

        $this->assertInstanceOf(TriggerInterface::class, $trigger);
        $this->assertSame('test.trigger', $trigger->getId());
    }
}
