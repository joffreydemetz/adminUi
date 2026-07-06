<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\AdminUi\List;

use JDZ\Renderer\Button;
use JDZ\Renderer\Element;
use JDZ\Renderer\ElementsTrait;

/**
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class ItemActions extends Element
{
  use ElementsTrait;

  protected string $renderer = 'itemActions';

  public function addButton(string $name, string $style = '', string $icon = ''): Button
  {
    $button = new Button($name);

    if ($style) {
      $button->addStyle('btn-' . $style);
    }

    if ($icon) {
      $button->setIcon('glyphicons glyphicons-' . $icon);
    }

    return $this->addElement($button);
  }

  public function getItemAction(string $name, string $href, string $style = 'default', ?string $glyphicon = null, ?string $text = null): Button
  {
    if ($this->hasElement($name)) {
      return $this->getElement($name);
    }

    $button = new Button($name);
    $button->addStyle('btn btn-' . $style);
    $button->setTag('a');
    $button->setHref($href);
    if ($glyphicon) {
      $button->setIcon('glyphicons glyphicons-' . $glyphicon);
    }
    if ($text) {
      $button->setText($text);
    }

    $this->addElement($button);

    return $button;
  }

  public function toData(): array
  {
    $data = parent::toData();

    $data['buttons'] = $this->renderElements();

    return $data;
  }
}
