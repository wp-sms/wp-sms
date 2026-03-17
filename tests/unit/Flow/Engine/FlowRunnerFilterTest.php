<?php

namespace WSms\Tests\Unit\Flow\Engine;

use PHPUnit\Framework\TestCase;
use WSms\Flow\Engine\FlowRunner;

class FlowRunnerFilterTest extends TestCase
{
    private FlowRunner $runner;

    protected function setUp(): void
    {
        // We only need matchesTriggerFilters() which is a pure method,
        // so we can use reflection or just instantiate with mocks.
        $this->runner = new FlowRunner(
            $this->createMock(\WSms\Flow\Trigger\TriggerRegistry::class),
            $this->createMock(\WSms\Flow\Contracts\FlowRepositoryInterface::class),
            $this->createMock(\WSms\Flow\Engine\FlowExecutor::class),
            $this->createMock(\WSms\Flow\Storage\FlowExecutionRepository::class),
            $this->createMock(\WSms\Event\Contracts\EventDispatcherInterface::class),
            $this->createMock(\WSms\Dependencies\Psr\Log\LoggerInterface::class),
        );
    }

    public function testEmptyFiltersMatchEverything(): void
    {
        $this->assertTrue($this->runner->matchesTriggerFilters([], ['post_type' => 'post']));
    }

    public function testNullFilterValueMatchesEverything(): void
    {
        $this->assertTrue($this->runner->matchesTriggerFilters(
            ['post_type' => null],
            ['post_type' => 'post'],
        ));
    }

    public function testEmptyStringFilterValueMatchesEverything(): void
    {
        $this->assertTrue($this->runner->matchesTriggerFilters(
            ['post_type' => ''],
            ['post_type' => 'post'],
        ));
    }

    public function testEmptyArrayFilterValueMatchesEverything(): void
    {
        $this->assertTrue($this->runner->matchesTriggerFilters(
            ['status' => []],
            ['status' => 'completed'],
        ));
    }

    public function testStringFilterMatchesExactValue(): void
    {
        $this->assertTrue($this->runner->matchesTriggerFilters(
            ['post_type' => 'product'],
            ['post_type' => 'product', 'post_title' => 'Hello'],
        ));
    }

    public function testStringFilterRejectsNonMatchingValue(): void
    {
        $this->assertFalse($this->runner->matchesTriggerFilters(
            ['post_type' => 'product'],
            ['post_type' => 'post', 'post_title' => 'Hello'],
        ));
    }

    public function testMultipleFiltersAllMustMatch(): void
    {
        $this->assertTrue($this->runner->matchesTriggerFilters(
            ['post_type' => 'product', 'new_status' => 'publish'],
            ['post_type' => 'product', 'new_status' => 'publish', 'old_status' => 'draft'],
        ));

        $this->assertFalse($this->runner->matchesTriggerFilters(
            ['post_type' => 'product', 'new_status' => 'publish'],
            ['post_type' => 'product', 'new_status' => 'draft', 'old_status' => 'publish'],
        ));
    }

    public function testFilterKeyNotInPayloadReturnsFalse(): void
    {
        $this->assertFalse($this->runner->matchesTriggerFilters(
            ['post_type' => 'product'],
            ['post_title' => 'Hello'],
        ));
    }

    public function testArrayFilterUsesInArray(): void
    {
        // Multi-select: payload value must be in the filter array
        $this->assertTrue($this->runner->matchesTriggerFilters(
            ['status' => ['completed', 'processing']],
            ['status' => 'completed'],
        ));

        $this->assertFalse($this->runner->matchesTriggerFilters(
            ['status' => ['completed', 'processing']],
            ['status' => 'pending'],
        ));
    }

    public function testStringCastHandlesIntStringMismatch(): void
    {
        // product_id stored as int in payload, string in filter
        $this->assertTrue($this->runner->matchesTriggerFilters(
            ['product_id' => '55'],
            ['product_id' => 55],
        ));

        // Reverse: int filter, string payload
        $this->assertTrue($this->runner->matchesTriggerFilters(
            ['product_id' => 55],
            ['product_id' => '55'],
        ));
    }

    public function testMixedEmptyAndActiveFilters(): void
    {
        // Only the non-empty filter should be checked
        $this->assertTrue($this->runner->matchesTriggerFilters(
            ['post_type' => 'product', 'new_status' => ''],
            ['post_type' => 'product', 'new_status' => 'publish'],
        ));

        $this->assertFalse($this->runner->matchesTriggerFilters(
            ['post_type' => 'page', 'new_status' => ''],
            ['post_type' => 'product', 'new_status' => 'publish'],
        ));
    }
}
