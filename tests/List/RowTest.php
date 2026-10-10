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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Row::class)]
class RowTest extends TestCase
{
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

    public function testToRowDataEmitsTheColumnValuesActionsAndTriggersOnly(): void
    {
        $columns = new Columns('things');
        $columns->createColumn('id', 'id', '#');
        $columns->createColumn('label', 'label', 'Titre');

        $row = new Row(['id' => 3, 'label' => 'Trois', 'secret' => 'not a column']);
        $row->getButtons()->getItemAction('edit', '/admin/things/edit/?id=3', 'primary');
        $row->getTriggers()->getItemTrigger('publish', 'unpublish');

        $this->assertSame([
            'id' => 3,
            'label' => 'Trois',
            'actions' => [
                [
                    'renderer' => 'button',
                    'attrs' => ['class' => 'btn btn-primary', 'href' => '/admin/things/edit/?id=3'],
                    'position' => 1,
                    'name' => 'edit',
                    'tag' => 'a',
                    'text' => '',
                ],
            ],
            'triggers' => [
                'renderer' => 'children',
                'attrs' => [],
                'triggers' => [
                    [
                        'renderer' => 'button',
                        'attrs' => ['class' => 'unpublish', 'href' => '#'],
                        'position' => 1,
                        'name' => 'publish',
                        'tag' => 'a',
                        'text' => '',
                    ],
                ],
            ],
        ], $row->toRowData($columns));
    }

    public function testADisabledRowWithoutMessageStillSaysSo(): void
    {
        $row = (new Row(['id' => 1]))->disable();

        $data = $row->toRowData(new Columns('things'));

        $this->assertTrue($data['rowIsDisabled']);
        $this->assertSame('', $data['disabledMessage']);
    }
}