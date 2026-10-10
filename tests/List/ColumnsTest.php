<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\AdminUi\Tests\List;

use JDZ\AdminUi\List\Column;
use JDZ\AdminUi\List\Columns;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The list header : the column type is what items.tmpl switches its cell
 * renderer on, and type + name are the classes the admin CSS sizes them by.
 */
#[CoversClass(Columns::class)]
#[CoversClass(Column::class)]
class ColumnsTest extends TestCase
{
  #[DataProvider('columnTypes')]
  public function testCreateColumnRendersItsTypeAndName(string $name, string $type, string $expectedType, string $expectedClass): void
  {
    $columns = new Columns('things');
    $columns->createColumn($name, $type, 'Titre');

    $this->assertSame([
      'renderer' => 'column',
      'attrs' => ['class' => $expectedClass],
      'position' => 1,
      'name' => $name,
      'type' => $expectedType,
      'title' => 'Titre',
      'hidden' => false,
    ], $columns->toData()['columns'][0]);
  }

  /** The six columns AdminControllerTrait::buildColumns() declares, plus a site's own. */
  public static function columnTypes(): array
  {
    return [
      'checkbox, type from the name' => ['name' => 'checkbox', 'type' => '', 'expectedType' => 'checkbox', 'expectedClass' => 'checkbox'],
      'label' => ['name' => 'label', 'type' => 'label', 'expectedType' => 'label', 'expectedClass' => 'label'],
      'published as boolean' => ['name' => 'published', 'type' => 'boolean', 'expectedType' => 'boolean', 'expectedClass' => 'boolean published'],
      'ordering' => ['name' => 'ordering', 'type' => 'ordering', 'expectedType' => 'ordering', 'expectedClass' => 'ordering'],
      'id' => ['name' => 'id', 'type' => 'id', 'expectedType' => 'id', 'expectedClass' => 'id'],
      'triggers, type from the name' => ['name' => 'triggers', 'type' => '', 'expectedType' => 'triggers', 'expectedClass' => 'triggers'],
      'a site column with its own type' => ['name' => 'price', 'type' => 'number', 'expectedType' => 'number', 'expectedClass' => 'number price'],
    ];
  }

  public function testHiddenAndWidthReachTheHeader(): void
  {
    $columns = new Columns('things');
    $columns->createColumn('id', 'id', '#', true, '4rem');

    $column = $columns->toData()['columns'][0];

    $this->assertTrue($column['hidden']);
    $this->assertSame(['class' => 'id', 'style' => 'width:4rem'], $column['attrs']);
  }

  public function testCreatingAnExistingColumnReturnsItUnchanged(): void
  {
    $columns = new Columns('things');
    $first = $columns->createColumn('label', 'label', 'Titre');

    $again = $columns->createColumn('label', 'text', 'Autre', true, '50%');

    $this->assertSame($first, $again);
    $this->assertSame([
      'renderer' => 'column',
      'attrs' => ['class' => 'label'],
      'position' => 1,
      'name' => 'label',
      'type' => 'label',
      'title' => 'Titre',
      'hidden' => false,
    ], $columns->toData()['columns'][0]);
  }

  public function testColumnsRenderInCreationOrder(): void
  {
    $columns = new Columns('things');
    foreach (['checkbox', 'label', 'published', 'ordering', 'id', 'triggers'] as $name) {
      $columns->createColumn($name);
    }

    $data = $columns->toData();

    $this->assertSame('columns', $data['renderer']);
    $this->assertSame(
      ['checkbox', 'label', 'published', 'ordering', 'id', 'triggers'],
      \array_column($data['columns'], 'name'),
    );
    $this->assertSame([1, 2, 3, 4, 5, 6], \array_column($data['columns'], 'position'));
  }

  public function testAColumnReconfiguredBeforeRenderingRendersTheNewValues(): void
  {
    $column = new Column('published', 'boolean', 'Publié');
    $column->setType('toggle')->setTitle('En ligne')->withHidden();

    $this->assertSame([
      'renderer' => 'column',
      'attrs' => ['class' => 'toggle published'],
      'name' => 'published',
      'type' => 'toggle',
      'title' => 'En ligne',
      'hidden' => true,
    ], $column->toData());
  }
}
