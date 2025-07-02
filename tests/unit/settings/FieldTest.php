<?php
use PHPUnit\Framework\TestCase;

use WP_SMS\Settings\Field;

class FieldTest extends TestCase
{
    public function testConstructorSetsProperties()
    {
        $args = [
            'key' => 'test_key',
            'type' => 'text',
            'label' => 'Test Label',
            'description' => 'Test Description',
            'default' => 'default_value',
            'group_label' => 'Group',
            'section' => 'Section',
            'options' => ['a', 'b'],
            'order' => 1,
            'doc' => 'Doc',
            'show_if' => null,
            'hide_if' => null,
            'validate_callback' => function ($v) { return true; },
            'sanitize_callback' => function ($v) { return $v; },
            'repeatable' => true,
            'tag' => 'tag',
            'readonly' => true,
            'options_depends_on' => null,
            'sortable' => false,
            'field_groups' => [],
            'placeholder' => 'Placeholder',
            'min' => 0,
            'max' => 10,
            'step' => 1,
            'rows' => 2,
            'hidden' => false,
            'autoSaveAndRefresh' => false,
        ];
        $field = new Field($args);
        $this->assertEquals('test_key', $field->key);
        $this->assertEquals('text', $field->type);
        $this->assertEquals('Test Label', $field->label);
        $this->assertEquals('Test Description', $field->description);
        $this->assertEquals('default_value', $field->default);
        $this->assertEquals('Group', $field->groupLabel);
        $this->assertEquals('Section', $field->section);
        $this->assertEquals(['a', 'b'], $field->options);
        $this->assertEquals(1, $field->order);
        $this->assertEquals('Doc', $field->doc);
        $this->assertTrue($field->repeatable);
        $this->assertEquals('tag', $field->tag);
        $this->assertTrue($field->readonly);
        $this->assertEquals('Placeholder', $field->placeholder);
        $this->assertEquals(0, $field->min);
        $this->assertEquals(10, $field->max);
        $this->assertEquals(1, $field->step);
        $this->assertEquals(2, $field->rows);
        $this->assertFalse($field->hidden);
        $this->assertFalse($field->autoSaveAndRefresh);
    }

    public function testGetKey()
    {
        $field = new Field(['key' => 'abc', 'type' => 'text']);
        $this->assertEquals('abc', $field->getKey());
    }

    public function testGetValidateCallback()
    {
        $callback = function ($v) { return $v === 'ok'; };
        $field = new Field(['key' => 'a', 'type' => 'text', 'validate_callback' => $callback]);
        $this->assertIsCallable($field->getValidateCallback());
        $this->assertTrue(($field->getValidateCallback())('ok'));
    }

    public function testGetSanitizeCallback()
    {
        $callback = function ($v) { return strtoupper($v); };
        $field = new Field(['key' => 'a', 'type' => 'text', 'sanitize_callback' => $callback]);
        $this->assertIsCallable($field->getSanitizeCallback());
        $this->assertEquals('FOO', ($field->getSanitizeCallback())('foo'));
    }

    public function testGetOptions()
    {
        $field = new Field(['key' => 'a', 'type' => 'select', 'options' => ['x', 'y']]);
        $this->assertEquals(['x', 'y'], $field->getOptions());
    }

    public function testHasTag()
    {
        $field = new Field(['key' => 'a', 'type' => 'text', 'tag' => 'new']);
        $this->assertTrue($field->hasTag());
        $field2 = new Field(['key' => 'b', 'type' => 'text']);
        $this->assertFalse($field2->hasTag());
    }

    public function testIsReadonly()
    {
        $field = new Field(['key' => 'a', 'type' => 'text', 'readonly' => true]);
        $this->assertTrue($field->isReadonly());
        $field2 = new Field(['key' => 'b', 'type' => 'text', 'readonly' => false]);
        $this->assertFalse($field2->isReadonly());
    }
} 