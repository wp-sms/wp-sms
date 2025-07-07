<?php
use PHPUnit\Framework\TestCase;
use WP_SMS\RestEndpoints\Endpoints\V1\Settings\GetSettingsEndpoint;
use WP_SMS\Settings\SchemaRegistry;

class GetSettingsEndpointTest extends TestCase
{
    public function testResolveValuesMergesSavedAndDefaults()
    {
        $group = SchemaRegistry::instance()->getGroup('general');
        $fields = $group->getFields();
        $key = $fields[0]->getKey();
        $default = $fields[0]->default;

        // Set the option value using the actual Option class
        \WP_SMS\Option::updateOption($key, 'custom_value', null);

        $ref = new \ReflectionClass(GetSettingsEndpoint::class);
        $method = $ref->getMethod('resolveValues');
        $method->setAccessible(true);
        $result = $method->invoke(null, [$group]);
        $this->assertArrayHasKey($key, $result);
        $this->assertEquals('custom_value', $result[$key]);

        // Clean up: restore the default value
        \WP_SMS\Option::updateOption($key, $default, null);
    }

    public function testResolveValuesFallsBackToDefault()
    {
        $group = SchemaRegistry::instance()->getGroup('general');
        $fields = $group->getFields();
        $key = $fields[0]->getKey();
        $default = $fields[0]->default;

        // Remove the option value to test fallback
        \WP_SMS\Option::updateOption($key, null, null);

        $ref = new \ReflectionClass(GetSettingsEndpoint::class);
        $method = $ref->getMethod('resolveValues');
        $method->setAccessible(true);
        $result = $method->invoke(null, [$group]);
        $this->assertArrayHasKey($key, $result);
        $this->assertEquals($default, $result[$key]);
    }
} 