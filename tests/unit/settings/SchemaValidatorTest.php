<?php
use PHPUnit\Framework\TestCase;
use WP_SMS\Settings\SchemaValidator;
use WP_SMS\Settings\SchemaRegistry;

class SchemaValidatorTest extends TestCase
{
    public function testValidateReturnsCleanedValuesForValidInput()
    {
        $schema = SchemaRegistry::instance()->all();
        // Pick a field from the 'general' group (should exist)
        $group = SchemaRegistry::instance()->getGroup('general');
        $fields = $group->getFields();
        $firstField = $fields[0];
        $key = $firstField->getKey();
        $input = [$key => $firstField->default ?? 'test'];
        list($cleaned, $errors) = SchemaValidator::validate($input);
        $this->assertArrayHasKey($key, $cleaned);
        $this->assertEmpty($errors);
    }

    public function testValidateReturnsErrorsForInvalidInput()
    {
        $group = SchemaRegistry::instance()->getGroup('general');
        $fields = $group->getFields();
        // Find a field with type 'number' if possible
        $numberField = null;
        foreach ($fields as $field) {
            if ($field->type === 'number') {
                $numberField = $field;
                break;
            }
        }
        if ($numberField) {
            $key = $numberField->getKey();
            $input = [$key => 'not_a_number'];
            list($cleaned, $errors) = SchemaValidator::validate($input);
            $this->assertArrayHasKey($key, $errors);
        } else {
            $this->markTestSkipped('No number field found in general group.');
        }
    }

    public function testSanitizeSingleReturnsSanitizedValue()
    {
        $group = SchemaRegistry::instance()->getGroup('general');
        $fields = $group->getFields();
        $firstField = $fields[0];
        $key = $firstField->getKey();
        $value = '  test  ';
        // If the field has a sanitize callback, it should trim or otherwise process the value
        $sanitized = SchemaValidator::sanitizeSingle($key, $value);
        $this->assertNotNull($sanitized);
    }

    public function testGetFieldByKeyReturnsFieldOrNull()
    {
        $group = SchemaRegistry::instance()->getGroup('general');
        $fields = $group->getFields();
        $firstField = $fields[0];
        $key = $firstField->getKey();

        $ref = new ReflectionClass(SchemaValidator::class);
        $method = $ref->getMethod('getFieldByKey');
        $method->setAccessible(true);

        $result = $method->invoke(null, $key);
        $this->assertNotNull($result);
        $this->assertEquals($key, $result->getKey());

        $resultNull = $method->invoke(null, 'nonexistent_key_123');
        $this->assertNull($resultNull);
    }
} 