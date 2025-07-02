<?php
use PHPUnit\Framework\TestCase;
use WP_SMS\Settings\SchemaRegistry;
use WP_SMS\Settings\Abstracts\AbstractSettingGroup;

class SchemaRegistryTest extends TestCase
{
    public function testInstanceReturnsSingleton()
    {
        $instance1 = SchemaRegistry::instance();
        $instance2 = SchemaRegistry::instance();
        $this->assertSame($instance1, $instance2);
    }

    public function testGetGroupReturnsGroupByName()
    {
        $registry = SchemaRegistry::instance();
        // Use a known group name from the default registry, e.g., 'general' or 'gateway'
        $group = $registry->getGroup('general');
        $this->assertInstanceOf(AbstractSettingGroup::class, $group);
        $this->assertEquals('general', $group->getName());
    }

    public function testGetCategoryReturnsGroups()
    {
        $registry = SchemaRegistry::instance();
        $groups = $registry->getCategory('core');
        $this->assertIsArray($groups);
        $this->assertNotEmpty($groups);
        $firstGroup = reset($groups);
        $this->assertIsArray($firstGroup); // getCategory returns array of fields per group
    }

    public function testAllReturnsAllGroups()
    {
        $registry = SchemaRegistry::instance();
        $all = $registry->all();
        $this->assertIsArray($all);
        $this->assertNotEmpty($all);
        $first = reset($all);
        $this->assertInstanceOf(AbstractSettingGroup::class, $first);
    }

    public function testNestedPathsAreRegisteredCorrectly()
    {
        $reflection = new ReflectionClass(SchemaRegistry::class);
        $property = $reflection->getProperty('nestedPaths');
        $property->setAccessible(true);
        $nestedPaths = $property->getValue();

        $this->assertIsArray($nestedPaths);
        $this->assertNotEmpty($nestedPaths);
        // Check that at least one nested path is present and is a string path
        $found = false;
        foreach ($nestedPaths as $groupName => $path) {
            if (is_string($path) && strpos($path, '.') !== false) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'At least one nested path should be registered with dot notation.');
    }
} 