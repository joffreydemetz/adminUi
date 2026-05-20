<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\AdminUi\List;

use JDZ\Renderer\Button;

/**
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class Trigger extends Button
{
  private string $type = '';

  public function setType(string $type)
  {
    $this->type = $type;
    return $this;
  }

  public function toData(): array
  {
    if ($this->type) {
      $this->addStyle($this->type);
    }

    return parent::toData();
  }
}
