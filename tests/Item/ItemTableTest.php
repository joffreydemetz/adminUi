<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\AdminUi\Tests\Item;

use JDZ\AdminUi\Item\ItemTable;
use JDZ\AdminUi\List\Column;
use JDZ\AdminUi\List\Columns;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * A table inside an item preview (purchase lines, …) : columns as the list
 * declares them, rows passed through untouched.
 */
#[CoversClass(ItemTable::class)]
#[CoversClass(Columns::class)]
#[CoversClass(Column::class)]
class ItemTableTest extends TestCase
{
  public function testToDataCarriesTheColumnsThenTheRowsAsGiven(): void
  {
    $table = new ItemTable('purchases');
    $table->addColumn('label', '', 'Article');
    $table->addColumn('qty', 'number', 'Qté', false, '10%');
    $table->addColumn('ref', 'id', 'Réf', true);
    $table->addRow(['label' => 'Stage', 'qty' => 2]);
    $table->addRow(['label' => '<b>raw</b>', 'extra' => null]);

    $this->assertSame([
      'renderer' => 'item.table',
      'attrs' => [],
      'columns' => [
        [
          'renderer' => 'column',
          'attrs' => ['class' => 'label'],
          'position' => 1,
          'name' => 'label',
          'type' => 'label',
          'title' => 'Article',
          'hidden' => false,
        ],
        [
          'renderer' => 'column',
          'attrs' => ['class' => 'number qty', 'style' => 'width:10%'],
          'position' => 2,
          'name' => 'qty',
          'type' => 'number',
          'title' => 'Qté',
          'hidden' => false,
        ],
        [
          'renderer' => 'column',
          'attrs' => ['class' => 'id ref'],
          'position' => 3,
          'name' => 'ref',
          'type' => 'id',
          'title' => 'Réf',
          'hidden' => true,
        ],
      ],
      'rows' => [
        ['label' => 'Stage', 'qty' => 2],
        ['label' => '<b>raw</b>', 'extra' => null],
      ],
    ], $table->toData());
  }

  public function testAnEmptyTableRendersNoColumnAndNoRow(): void
  {
    $data = (new ItemTable('purchases'))->toData();

    $this->assertSame([], $data['columns']);
    $this->assertSame([], $data['rows']);
  }

  public function testAddingAColumnTwiceKeepsTheFirstDeclaration(): void
  {
    $table = new ItemTable('purchases');
    $first = $table->addColumn('qty', 'number', 'Qté');

    $this->assertSame($first, $table->addColumn('qty', 'text', 'Quantité', true, '5em'));

    $columns = $table->toData()['columns'];
    $this->assertCount(1, $columns);
    $this->assertSame(['number', 'Qté', false], [$columns[0]['type'], $columns[0]['title'], $columns[0]['hidden']]);
    $this->assertArrayNotHasKey('style', $columns[0]['attrs']);
  }

  public function testTheColumnsCollectionIsTheOneAddColumnFills(): void
  {
    $table = new ItemTable('purchases');
    $table->getColumns()->createColumn('total', 'price', 'Total');

    $this->assertSame(['total'], \array_column($table->toData()['columns'], 'name'));
    $this->assertSame('purchases_columns', $table->getColumns()->getName());
  }

  public function testSetRowsReplacesAndAddRowAppends(): void
  {
    $table = new ItemTable('purchases');
    $table->addRow(['label' => 'old']);
    $table->setRows([['label' => 'a'], ['label' => 'b']]);
    $table->addRow(['label' => 'c']);

    $this->assertSame([['label' => 'a'], ['label' => 'b'], ['label' => 'c']], $table->getRows());
    $this->assertSame($table->getRows(), $table->toData()['rows']);
  }
}
