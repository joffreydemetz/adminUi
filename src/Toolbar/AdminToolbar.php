<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\AdminUi\Toolbar;

/**
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class AdminToolbar extends Toolbar
{
  private string $title = '';
  private string $subtitle = '';

  public function __construct(string $name)
  {
    parent::__construct($name);

    $this->addStyle('ca-toolbar');
  }

  public function setTitle(string $title)
  {
    $this->title = $title;
    return $this;
  }

  public function setSubtitle(string $subtitle)
  {
    $this->subtitle = $subtitle;
    return $this;
  }

  public function toData(): array
  {
    foreach ($this->elements as $key => $button) {
      if ('divider' === $button->getType()) {
        $this->removeElement($key);
      }
      break;
    }

    $data = parent::toData();

    $data['title'] = $this->title;
    $data['subtitle'] = $this->subtitle;

    return $data;
  }
}
