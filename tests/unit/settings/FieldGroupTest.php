<?php
use PHPUnit\Framework\TestCase;
use WP_SMS\Settings\FieldGroup;
use WP_SMS\Settings\Field;

class FieldGroupTest extends TestCase
{
    public function testConstructorSetsProperties()
    {
        $args = [
            'key' => 'group1',
            'label' => 'Group Label',
            'description' => 'Group Description',
            'fields' => [],
            'order' => 3,
            'layout' => '3-column',
        ];
        $group = new FieldGroup($args);
        $this->assertEquals('group1', $group->key);
        $this->assertEquals('Group Label', $group->label);
        $this->assertEquals('Group Description', $group->description);
        $this->assertEquals([], $group->fields);
        $this->assertEquals(3, $group->order);
        $this->assertEquals('3-column', $group->layout);
    }

    public function testToArrayReturnsCorrectStructure()
    {
        $field = new Field(['key' => 'f1', 'type' => 'text']);
        $args = [
            'key' => 'group2',
            'label' => 'Label',
            'fields' => [$field],
        ];
        $group = new FieldGroup($args);
        $arr = $group->toArray();
        $this->assertEquals('group2', $arr['key']);
        $this->assertEquals('Label', $arr['label']);
        $this->assertArrayHasKey('fields', $arr);
        $this->assertIsArray($arr['fields']);
        $this->assertEquals('f1', $arr['fields'][0]['key']);
    }

    public function testAddFieldAppendsField()
    {
        $group = new FieldGroup(['key' => 'g', 'fields' => []]);
        $field = new Field(['key' => 'f2', 'type' => 'text']);
        $group->addField($field);
        $this->assertCount(1, $group->fields);
        $this->assertEquals('f2', $group->fields[0]->key);
    }

    public function testGetFieldsReturnsAllFields()
    {
        $field1 = new Field(['key' => 'f3', 'type' => 'text']);
        $field2 = new Field(['key' => 'f4', 'type' => 'text']);
        $group = new FieldGroup(['key' => 'g', 'fields' => [$field1, $field2]]);
        $fields = $group->getFields();
        $this->assertCount(2, $fields);
        $this->assertEquals('f3', $fields[0]->key);
        $this->assertEquals('f4', $fields[1]->key);
    }

    public function testHasFields()
    {
        $group = new FieldGroup(['key' => 'g', 'fields' => []]);
        $this->assertFalse($group->hasFields());
        $field = new Field(['key' => 'f5', 'type' => 'text']);
        $group->addField($field);
        $this->assertTrue($group->hasFields());
    }
} 