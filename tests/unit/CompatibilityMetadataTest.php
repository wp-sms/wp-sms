<?php

namespace unit;

use WP_UnitTestCase;

class CompatibilityMetadataTest extends WP_UnitTestCase
{
    public function testReadmeDeclaresWordPress71Compatibility(): void
    {
        $readme = file_get_contents(dirname(__DIR__, 2) . '/readme.txt');

        $this->assertMatchesRegularExpression('/^Tested up to:\s*7\.1$/m', $readme);
    }
}