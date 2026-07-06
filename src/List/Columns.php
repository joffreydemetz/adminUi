<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\AdminUi\List;

use JDZ\Renderer\Element;
use JDZ\Renderer\ElementsTrait;

/**
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class Columns extends Element
{
  use ElementsTrait;

  protected string $renderer = 'columns';

  public function __construct(string $name)
  {
    $this->name = $name;
  }

  public function toData(): array
  {
    $data = parent::toData();

    $data['columns'] = $this->renderElements();

    return $data;
  }

  public function createColumn(string $name, string $type = '', string $title = '', bool $hidden = false, string $size = ''): Column
  {
    if ($this->hasElement($name)) {
      return $this->getElement($name);
    }

    if ('' === $type) {
      $type = $name;
    }

    $column = new Column($name, $type, $title, $hidden, $size);
    $this->addElement($column);

    return $column;
  }
}
