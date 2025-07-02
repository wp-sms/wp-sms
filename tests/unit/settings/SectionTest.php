<?php
use PHPUnit\Framework\TestCase;
use WP_SMS\Settings\Section;
use WP_SMS\Settings\Field;

class SectionTest extends TestCase
{
    public function testConstructorSetsProperties()
    {
        $args = [
            'id' => 'section1',
            'title' => 'Section Title',
            'subtitle' => 'Section Subtitle',
            'help_url' => 'https://help.url',
            'tag' => 'new',
            'order' => 2,
            'fields' => [],
            'readonly' => true,
            'layout' => '2-column',
        ];
        $section = new Section($args);
        $this->assertEquals('section1', $section->id);
        $this->assertEquals('Section Title', $section->title);
        $this->assertEquals('Section Subtitle', $section->subtitle);
        $this->assertEquals('https://help.url', $section->helpUrl);
        $this->assertEquals('new', $section->tag);
        $this->assertEquals(2, $section->order);
        $this->assertEquals([], $section->fields);
        $this->assertTrue($section->readonly);
        $this->assertEquals('2-column', $section->layout);
    }

    public function testToArrayReturnsCorrectStructure()
    {
        $field = new Field(['key' => 'f1', 'type' => 'text']);
        $args = [
            'id' => 'section2',
            'title' => 'Title',
            'fields' => [$field],
        ];
        $section = new Section($args);
        $arr = $section->toArray();
        $this->assertEquals('section2', $arr['id']);
        $this->assertEquals('Title', $arr['title']);
        $this->assertArrayHasKey('fields', $arr);
        $this->assertIsArray($arr['fields']);
        $this->assertEquals('f1', $arr['fields'][0]['key']);
    }

    public function testAddFieldAppendsField()
    {
        $section = new Section(['id' => 's', 'title' => 't', 'fields' => []]);
        $field = new Field(['key' => 'f2', 'type' => 'text']);
        $section->addField($field);
        $this->assertCount(1, $section->fields);
        $this->assertEquals('f2', $section->fields[0]->key);
    }

    public function testSetFieldsReplacesFields()
    {
        $section = new Section(['id' => 's', 'title' => 't', 'fields' => []]);
        $field1 = new Field(['key' => 'f3', 'type' => 'text']);
        $field2 = new Field(['key' => 'f4', 'type' => 'text']);
        $section->setFields([$field1, $field2]);
        $this->assertCount(2, $section->fields);
        $this->assertEquals('f3', $section->fields[0]->key);
        $this->assertEquals('f4', $section->fields[1]->key);
    }
} 