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
}
