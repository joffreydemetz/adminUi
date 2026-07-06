<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\AdminUi\Item;

use JDZ\AdminUi\List\Column;
use JDZ\AdminUi\List\Columns;
use JDZ\Renderer\Element;
use JDZ\Renderer\ElementsTrait;

/**
 * ItemTable represents a table within an Item for displaying tabular data (e.g., purchases line items).
 * 
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class ItemTable extends Element
{
  use ElementsTrait;

  protected string $renderer = 'item.table';
  
  private Columns $columns;
  private array $rows = [];

  public function __construct(string $name)
  {
    $this->name = $name;
    $this->columns = new Columns($name . '_columns');
  }

  /**
   * Add a column to the table
   */
  public function addColumn(string $name, string $type = '', string $title = '', bool $hidden = false, string $size = ''): Column
  {
    return $this->columns->createColumn($name, $type, $title, $hidden, $size);
  }

  /**
   * Get the columns collection
   */
  public function getColumns(): Columns
  {
    return $this->columns;
  }

  /**
   * Add a row to the table
   */
  public function addRow(array $data): static
  {
    $this->rows[] = $data;
    return $this;
  }

  /**
   * Set all rows at once
   */
  public function setRows(array $rows): static
  {
    $this->rows = $rows;
    return $this;
  }

  /**
   * Get all rows
   */
  public function getRows(): array
  {
    return $this->rows;
  }

  public function toData(): array
  {
    $data = parent::toData();

    $data['columns'] = $this->columns->toData()['columns'];
    $data['rows'] = $this->rows;

    return $data;
  }
}