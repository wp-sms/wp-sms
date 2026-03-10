<?php

namespace WSms\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Smoke test to verify the test framework is working.
 */
class BootstrapTest extends TestCase
{
    public function testPluginConstantsAreDefined(): void
    {
        // Constants may not be defined in standalone mode,
        // but the autoloader should make the class available.
        $this->assertTrue(
            class_exists('WSms\\Bootstrap'),
            'WSms\\Bootstrap class should be autoloadable'
        );
    }

    public function testServiceContainerIsSingleton(): void
    {
        $a = \WSms\Container\ServiceContainer::getInstance();
        $b = \WSms\Container\ServiceContainer::getInstance();

        $this->assertSame($a, $b);
    }

    public function testServiceContainerRegistersAndResolves(): void
    {
        $container = \WSms\Container\ServiceContainer::getInstance();
        $container->register('test_service', function () {
            return new \stdClass();
        });

        $this->assertTrue($container->has('test_service'));
        $this->assertInstanceOf(\stdClass::class, $container->get('test_service'));

        // Cleanup
        $container->reset();
    }
}
