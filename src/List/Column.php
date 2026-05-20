<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\AdminUi\List;

use JDZ\Renderer\Element;

/**
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class Column extends Element
{
  protected string $renderer = 'column';

  private string $type;
  private string $title;
  private string $width;
  private bool $hidden;

  public function __construct(string $name, string $type, string $title, bool $hidden = false, string $width = '')
  {
    $this->name = $name;
    $this->type = $type;
    $this->title = $title;
    $this->hidden = $hidden;
    $this->width = $width;
  }

  public function setType(string $type)
  {
    $this->type = $type;
    return $this;
  }

  public function setTitle(string $title)
  {
    $this->title = $title;
    return $this;
  }

  public function withHidden(bool $hidden = true)
  {
    $this->hidden = $hidden;
    return $this;
  }

  public function toData(): array
  {
    $this->addStyle($this->type);
    $this->addStyle($this->name);

    $data = parent::toData();

    $data['name'] = $this->name;
    $data['type'] = $this->type;
    $data['title'] = $this->title;
    $data['hidden'] = $this->hidden;

    return $data;
  }

  public function renderAttrs(): array
  {
    $attrs = parent::renderAttrs();

    if ($this->width) {
      $attrs['style'] = 'width:' . $this->width;
    }

    return $attrs;
  }
}
