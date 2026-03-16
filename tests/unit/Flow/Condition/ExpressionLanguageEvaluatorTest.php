<?php

namespace WSms\Tests\Unit\Flow\Condition;

use PHPUnit\Framework\TestCase;
use WSms\Flow\Condition\ExpressionLanguageEvaluator;

class ExpressionLanguageEvaluatorTest extends TestCase
{
    private ExpressionLanguageEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new ExpressionLanguageEvaluator();
    }

    /**
     * @dataProvider expressionProvider
     */
    public function testEvaluateExpressions(string $expression, array $payload, bool $expected): void
    {
        $this->assertSame($expected, $this->evaluator->evaluate($expression, $payload));
    }

    public static function expressionProvider(): array
    {
        return [
            'simple comparison'     => ['total > 100', ['total' => 150], true],
            'simple false'          => ['total > 100', ['total' => 50], false],
            'equality'              => ['status == "active"', ['status' => 'active'], true],
            'not equal'             => ['status != "active"', ['status' => 'inactive'], true],
            'and operator'          => ['total > 100 and status == "vip"', ['total' => 200, 'status' => 'vip'], true],
            'or operator'           => ['total > 100 or status == "vip"', ['total' => 50, 'status' => 'vip'], true],
            'string contains'       => ['"hello world" matches "/world/"', [], true],
            'nested value'          => ['order > 0', ['order' => 5], true],
            'boolean true'          => ['active', ['active' => true], true],
            'boolean false'         => ['active', ['active' => false], false],
            'zero comparison'       => ['count == 0', ['count' => 0], true],
        ];
    }

    public function testInvalidExpressionReturnsFalse(): void
    {
        $this->assertFalse($this->evaluator->evaluate('invalid $$$ expression', []));
    }

    public function testMissingVariableReturnsFalse(): void
    {
        $this->assertFalse($this->evaluator->evaluate('nonexistent > 5', []));
    }
}
