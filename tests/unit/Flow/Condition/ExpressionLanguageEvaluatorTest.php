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
            'array in true'         => ['"admin" in roles', ['roles' => ['admin', 'editor']], true],
            'array in false'        => ['"subscriber" in roles', ['roles' => ['admin', 'editor']], false],
            'array not in false'    => ['"admin" not in roles', ['roles' => ['admin', 'editor']], false],
            'array not in true'     => ['"subscriber" not in roles', ['roles' => ['admin', 'editor']], true],
            'nested array in'       => ['"admin" in user["roles"]', ['user' => ['roles' => ['admin', 'editor']]], true],
            'nested array not in'   => ['"subscriber" in user["roles"]', ['user' => ['roles' => ['admin', 'editor']]], false],
            'numeric equals'        => ['order["id"] == 1001', ['order' => ['id' => 1001]], true],
            'numeric not equal'     => ['order["id"] == 1002', ['order' => ['id' => 1001]], false],
            'numeric greater than'  => ['order["id"] > 1000', ['order' => ['id' => 1001]], true],
            'array empty check'     => ['(roles == "" or roles == null)', ['roles' => []], true],
            'array not empty check' => ['(roles != "" and roles != null)', ['roles' => ['admin']], true],
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
