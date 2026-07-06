<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\AdminUi\Tests\List;

use JDZ\AdminUi\List\Row;
use JDZ\AdminUi\List\Columns;
use JDZ\AdminUi\List\Column;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JDZ\AdminUi\List\Row
 */
class RowTest extends TestCase
{
    public function testRowCreation(): void
    {
        $row = new Row(['id' => 1, 'name' => 'Test']);
        
        $this->assertEquals(1, $row->get('id'));
        $this->assertEquals('Test', $row->get('name'));
    }

    public function testRowButtonsAndTriggers(): void
    {
        $row = new Row();
        
        $buttons = $row->getButtons();
        $triggers = $row->getTriggers();
        
        $this->assertInstanceOf(\JDZ\AdminUi\List\ItemActions::class, $buttons);
        $this->assertInstanceOf(\JDZ\AdminUi\List\Triggers::class, $triggers);
    }

    public function testRowDisable(): void
    {
        $row = new Row();
        
        $this->assertFalse($row->isDisabled());
        $this->assertEquals('', $row->getDisabledMessage());
        
        $row->disable('Test message');
        
        $this->assertTrue($row->isDisabled());
        $this->assertEquals('Test message', $row->getDisabledMessage());
    }

    public function testRowToRowDataBasic(): void
    {
        $columns = new Columns('test');
        $columns->createColumn('id', 'number', 'ID');
        $columns->createColumn('name', 'text', 'Name');
        
        $row = new Row(['id' => 1, 'name' => 'Test Item']);
        
        $data = $row->toRowData($columns);
        
        $this->assertEquals(1, $data['id']);
        $this->assertEquals('Test Item', $data['name']);
        $this->assertArrayHasKey('actions', $data);
        $this->assertArrayHasKey('triggers', $data);
    }

    public function testRowToRowDataWithDisabled(): void
    {
        $columns = new Columns('test');
        $columns->createColumn('id', 'number', 'ID');
        $columns->createColumn('name', 'text', 'Name');
        
        $row = new Row(['id' => 1, 'name' => 'Test Item']);
        $row->disable('Item is disabled');
        
        $data = $row->toRowData($columns);
        
        $this->assertTrue($data['rowIsDisabled'] ?? false);
        $this->assertEquals('Item is disabled', $data['disabledMessage'] ?? '');
    }

    public function testRowToRowDataWithActionsAndTriggers(): void
    {
        $columns = new Columns('test');
        $columns->createColumn('id', 'number', 'ID');
        
        $row = new Row(['id' => 1]);
        
        // Add some actions
        $buttons = $row->getButtons();
        $buttons->getItemAction('edit', '/edit/1', 'primary', 'pencil');
        
        // Add some triggers  
        $triggers = $row->getTriggers();
        $triggers->getItemTrigger('delete', 'delete', 'trash');
        
        $data = $row->toRowData($columns);
        
        // Should have actions and triggers
        $this->assertArrayHasKey('actions', $data);
        $this->assertArrayHasKey('triggers', $data);
        
        // Check that triggers are properly structured
        $this->assertArrayHasKey('triggers', $data['triggers']);
    }

    public function testRowToRowDataEmptyColumnValues(): void
    {
        $columns = new Columns('test');
        $columns->createColumn('id', 'number', 'ID');
        $columns->createColumn('missing', 'text', 'Missing');
        
        $row = new Row(['id' => 1]); // missing column not set
        
        $data = $row->toRowData($columns);
        
        $this->assertEquals(1, $data['id']);
        $this->assertEquals('', $data['missing']); // Should default to empty string
    }

    public function testRowToRowDataFixesTriggersRegression(): void
    {
        // This test specifically verifies that the triggers regression is fixed
        $columns = new Columns('test');
        $columns->createColumn('id', 'number', 'ID');
        
        $row = new Row(['id' => 1]);
        
        // Add triggers (this was the missing piece in the original regression)
        $triggers = $row->getTriggers();
        $triggers->getItemTrigger('edit', 'edit', 'pencil');
        $triggers->getItemTrigger('delete', 'delete', 'trash');
        
        $data = $row->toRowData($columns);
        
        // The toRowData method should include triggers
        $this->assertArrayHasKey('triggers', $data);
        $this->assertNotEmpty($data['triggers']['triggers']);
    }

    public function testRowMagicMethods(): void
    {
        $row = new Row(['id' => 1, 'name' => 'Test']);
        
        // Test magic get
        $this->assertEquals(1, $row->id);
        $this->assertEquals('Test', $row->name);
        
        // Test magic set
        $row->title = 'New Title';
        $this->assertEquals('New Title', $row->title);
        
        // Test magic isset
        $this->assertTrue(isset($row->id));
        $this->assertFalse(isset($row->nonexistent));
    }
}