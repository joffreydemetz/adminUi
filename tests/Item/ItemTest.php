<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\AdminUi\Tests\Item;

use JDZ\AdminUi\Item\Item;
use JDZ\AdminUi\Item\ItemSection;
use JDZ\AdminUi\Item\ItemField;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JDZ\AdminUi\Item\Item
 * @covers \JDZ\AdminUi\Item\ItemSection
 * @covers \JDZ\AdminUi\Item\ItemField
 */
class ItemTest extends TestCase
{
    public function testItemCreation(): void
    {
        $item = new Item('test-item');
        
        $this->assertEquals('test-item', $item->getName());
        $this->assertEquals('item', $item->getRenderer());
    }

    public function testItemAddSection(): void
    {
        $item = new Item('test-item');
        $section = $item->addSection('general', 'General Information');
        
        $this->assertInstanceOf(ItemSection::class, $section);
        $this->assertTrue($item->hasSection('general'));
        $this->assertSame($section, $item->getSection('general'));
    }

    public function testItemToDataWithSections(): void
    {
        $item = new Item('test-item');
        $section = $item->addSection('general', 'General Information');
        $section->addField('name', 'text', 'Name', 'John Doe');
        
        $data = $item->toData();
        
        $this->assertEquals('item', $data['renderer']);
        $this->assertArrayHasKey('sections', $data);
        $this->assertCount(1, $data['sections']);
        $this->assertEquals('General Information', $data['sections'][0]['title']);
    }

    public function testItemSectionCreation(): void
    {
        $section = new ItemSection('general', 'General Information');
        
        $this->assertEquals('general', $section->getName());
        $this->assertEquals('General Information', $section->getTitle());
        $this->assertEquals('item.section', $section->getRenderer());
    }

    public function testItemSectionSetTitle(): void
    {
        $section = new ItemSection('general');
        $section->setTitle('Updated Title');
        
        $this->assertEquals('Updated Title', $section->getTitle());
    }

    public function testItemSectionAddField(): void
    {
        $section = new ItemSection('general', 'General Information');
        $field = $section->addField('name', 'text', 'Name', 'John Doe');
        
        $this->assertInstanceOf(ItemField::class, $field);
        $this->assertTrue($section->hasField('name'));
        $this->assertSame($field, $section->getField('name'));
    }

    public function testItemFieldCreation(): void
    {
        $field = new ItemField('name', 'text', 'Name', 'John Doe');
        
        $this->assertEquals('name', $field->getName());
        $this->assertEquals('text', $field->getType());
        $this->assertEquals('Name', $field->getLabel());
        $this->assertEquals('John Doe', $field->getValue());
        $this->assertEquals('item.field', $field->getRenderer());
    }

    public function testItemFieldTypes(): void
    {
        $field = new ItemField('test', ItemField::TYPE_TEXT, 'Test', 'value');
        $this->assertEquals(ItemField::TYPE_TEXT, $field->getType());
        
        $boolField = new ItemField('active', ItemField::TYPE_BOOL, 'Active', true);
        $this->assertEquals(ItemField::TYPE_BOOL, $boolField->getType());
    }

    public function testItemFieldSetters(): void
    {
        $field = new ItemField('name', 'text', 'Name');
        
        $field->setValue('Jane Doe');
        $this->assertEquals('Jane Doe', $field->getValue());
        
        $field->setLabel('Full Name');
        $this->assertEquals('Full Name', $field->getLabel());
        
        $field->setType('string');
        $this->assertEquals('string', $field->getType());
    }

    public function testItemFieldWithEmptyHidden(): void
    {
        $field = new ItemField('name', 'text', 'Name', '');
        $field->withEmptyHidden(true);
        
        $this->assertTrue($field->isEmptyHidden());
    }

    public function testItemFieldToData(): void
    {
        $field = new ItemField('email', 'text', 'Email', 'test@example.com');
        
        $data = $field->toData();
        
        $this->assertEquals('item.field', $data['renderer']);
        $this->assertEquals('email', $data['name']);
        $this->assertEquals('text', $data['type']);
        $this->assertEquals('Email', $data['label']);
        $this->assertEquals('test@example.com', $data['value']);
    }

    public function testItemFieldToDataWithEmptyValue(): void
    {
        $field = new ItemField('name', 'text', 'Name', '');
        $field->withEmptyHidden(true);
        
        $data = $field->toData();
        
        $this->assertTrue($data['hidden'] ?? false);
    }

    public function testItemFieldToDataBoolNeverHidden(): void
    {
        $field = new ItemField('active', 'bool', 'Active', false);
        $field->withEmptyHidden(true);
        
        $data = $field->toData();
        
        // Boolean fields should never be hidden even with emptyHidden=true
        $this->assertFalse($data['hidden'] ?? true);
    }

    public function testComplexItemStructure(): void
    {
        $item = new Item('user-preview');
        
        // Add General Information section
        $general = $item->addSection('general', 'General Information');
        $general->addField('id', 'id', 'ID', 123);
        $general->addField('name', 'text', 'Name', 'John Doe');
        $general->addField('email', 'email', 'Email', 'john@example.com');
        
        // Add Additional Information section
        $additional = $item->addSection('additional', 'Additional Information');
        $additional->addField('created', 'date', 'Created', '2023-01-01');
        $additional->addField('active', 'bool', 'Active', true);
        
        $data = $item->toData();
        
        $this->assertCount(2, $data['sections']);
        $this->assertEquals('General Information', $data['sections'][0]['title']);
        $this->assertEquals('Additional Information', $data['sections'][1]['title']);
    }
}