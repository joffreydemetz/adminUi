<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\AdminUi\Toolbar;

use JDZ\Renderer\Button;

/**
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class ToolbarButton extends Button
{
  protected string $renderer = 'toolbar.button';
  private string $type = '';

  public function __construct(string $name)
  {
    parent::__construct($name);

    $this->addStyle('btn');
    $this->addDataAttr('task', $this->name);

    if ('' === $this->type) {
      $this->type = $this->name;
    }
  }

  public function getType(): string
  {
    return $this->type;
  }

  public function setType(string $type)
  {
    $this->type = $type;
    return $this;
  }

  public function toData(): array
  {
    $data = parent::toData();

    if ('' !== $this->type) {
      $data['type'] = $this->type;
    }

    return $data;
  }

  protected function validate(): void
  {
    // divider strip separators render as <span class="divider"> — no link semantics
    if ('divider' === $this->type && 'span' === $this->tag) {
      return;
    }

    parent::validate();
  }
}
