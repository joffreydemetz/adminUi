<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\AdminUi\Toolbar;

use JDZ\Renderer\Contract\ElementableInterface;
use JDZ\Renderer\Element;
use JDZ\Renderer\ElementsTrait;

/**
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class Toolbar extends Element implements ElementableInterface
{
  use ElementsTrait;

  protected string $renderer = 'toolbar';

  public function __construct(string $name)
  {
    $this->name = $name;
  }

  public function addButton(string $name): ToolbarButton
  {
    return $this->addElement(new ToolbarButton($name));
  }

  public function toData(): array
  {
    $data = parent::toData();

    $data['buttons'] = $this->renderElements();

    return $data;
  }
}
