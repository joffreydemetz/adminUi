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
class Triggers extends Element
{
  use ElementsTrait;

  protected string $renderer = 'children';

  public function toData(): array
  {
    $data = parent::toData();

    $data['triggers'] = $this->renderElements();

    return $data;
  }

  public function getItemTrigger(string $name, ?string $type = null, ?string $glyphicon = null): Trigger
  {
    if ($this->hasElement($name)) {
      return $this->getElement($name);
    }

    if (!$type) {
      $type = $name;
    }

    $trigger = new Trigger($name);
    $trigger->setType($type);
    if ($glyphicon) {
      $trigger->setIcon('glyphicons glyphicons-' . $glyphicon);
    }

    $this->addElement($trigger);

    return $trigger;
  }
}
