<?php

namespace WSms\Tests\Unit\Messaging\Template;

use PHPUnit\Framework\TestCase;
use WSms\Messaging\Template\MustacheEngine;

class MustacheEngineTest extends TestCase
{
    private MustacheEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new MustacheEngine();
    }

    public function testSimpleReplacement(): void
    {
        $result = $this->engine->render('Hello {{name}}!', ['name' => 'World']);
        $this->assertSame('Hello World!', $result);
    }

    public function testDotNotation(): void
    {
        $result = $this->engine->render('Order #{{order.id}} total: ${{order.total}}', [
            'order' => ['id' => 123, 'total' => '99.99'],
        ]);
        $this->assertSame('Order #123 total: $99.99', $result);
    }

    public function testDeepNesting(): void
    {
        $result = $this->engine->render('{{a.b.c}}', [
            'a' => ['b' => ['c' => 'deep']],
        ]);
        $this->assertSame('deep', $result);
    }

    public function testMissingKeyPreservesPlaceholder(): void
    {
        $result = $this->engine->render('Hi {{unknown}}', []);
        $this->assertSame('Hi {{unknown}}', $result);
    }

    public function testSpacesInKey(): void
    {
        $result = $this->engine->render('{{ name }}', ['name' => 'trimmed']);
        $this->assertSame('trimmed', $result);
    }

    public function testIntegerValues(): void
    {
        $result = $this->engine->render('Count: {{count}}', ['count' => 42]);
        $this->assertSame('Count: 42', $result);
    }

    public function testArrayValueSerializesToJson(): void
    {
        $result = $this->engine->render('Data: {{data}}', ['data' => ['a' => 1]]);
        $this->assertSame('Data: {"a":1}', $result);
    }

    public function testEmptyTemplate(): void
    {
        $result = $this->engine->render('', ['name' => 'test']);
        $this->assertSame('', $result);
    }

    public function testNoPlaceholders(): void
    {
        $result = $this->engine->render('No placeholders here', ['name' => 'test']);
        $this->assertSame('No placeholders here', $result);
    }

    public function testMultiplePlaceholders(): void
    {
        $result = $this->engine->render('{{first}} {{last}}', ['first' => 'John', 'last' => 'Doe']);
        $this->assertSame('John Doe', $result);
    }
}
