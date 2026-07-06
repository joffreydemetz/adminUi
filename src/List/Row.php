<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\AdminUi\List;

use JDZ\Utils\Data as jData;

/**
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class Row extends jData implements RowInterface
{
  private ItemActions $buttons;
  private Triggers $triggers;
  private bool $disabled = false;
  private string $disabledMessage = '';

  public function __construct(array $data = [])
  {
    $this->data = $data;
    $this->buttons = new ItemActions();
    $this->triggers = new Triggers();
  }

  public function getButtons(): ItemActions
  {
    return $this->buttons;
  }

  public function getTriggers(): Triggers
  {
    return $this->triggers;
  }

  public function isDisabled(): bool
  {
    return $this->disabled;
  }

  public function getDisabledMessage(): string
  {
    return $this->disabledMessage;
  }

  public function disable(string $message = ''): static
  {
    $this->disabled = true;
    $this->disabledMessage = $message;
    return $this;
  }

  public function __get(string $name): mixed
  {
    return $this->get($name);
  }

  public function __set(string $name, mixed $value): void
  {
    $this->set($name, $value);
  }

  public function __isset(string $name): bool
  {
    return $this->has($name);
  }

  /**
   * Convert the row to an array suitable for list view rendering.
   * This includes column values, row state, actions, and triggers.
   * 
   * This method fixes the triggers regression where per-row edit/trash/reorder
   * anchors were not being emitted in 5.0 (legacy christannaProd handled this).
   * 
   * @param Columns $columns The columns collection to extract values for
   * @return array The row data with all required fields for list rendering
   */
  public function toRowData(Columns $columns): array
  {
    $row = [];
    
    // Copy column values from the data bag
    foreach ($columns->getElements() as $column) {
      $colName = $column->getName();
      $row[$colName] = $this->get($colName, '');
    }
    
    // Add row disabled state
    if ($this->isDisabled()) {
      $row['rowIsDisabled'] = true;
      $row['disabledMessage'] = $this->getDisabledMessage();
    }
    
    // Add actions from ItemActions
    $row['actions'] = $this->getButtons()->toData()['buttons'];
    
    // Add triggers from Triggers - THIS FIXES THE REGRESSION
    $row['triggers'] = $this->getTriggers()->toData();
    
    return $row;
  }
}
