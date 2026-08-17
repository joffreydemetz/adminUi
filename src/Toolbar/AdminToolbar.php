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
    // Drop dangling dividers — leading, trailing and doubled ones. Dividers
    // are declared around contextual buttons (beforePublish, beforeTrash, ...)
    // that may not have been added.
    $prevWasDivider = true;
    $lastDividerKey = null;
    foreach ($this->elements as $key => $button) {
      if ('divider' === $button->getType()) {
        if ($prevWasDivider) {
          $this->removeElement($key);
          continue;
        }
        $prevWasDivider = true;
        $lastDividerKey = $key;
      } else {
        $prevWasDivider = false;
        $lastDividerKey = null;
      }
    }
    if (null !== $lastDividerKey) {
      $this->removeElement($lastDividerKey);
    }

    $data = parent::toData();

    $data['title'] = $this->title;
    $data['subtitle'] = $this->subtitle;

    return $data;
  }

  public function getToolbarButton(string $name, ?string $type = null, ?string $glyphicon = null): ToolbarButton
  {
    if ($this->hasElement($name)) {
      return $this->getElement($name);
    }

    if (!$type) {
      $type = $name;
    }

    $button = new ToolbarButton($name);
    $button->setTag('a');
    $button->setType($type);

    if ($glyphicon) {
      $button->setIcon('glyphicons glyphicons-' . $glyphicon);
    }

    if ('divider' === $type) {
      $button->removeStyle('btn');
      $button->addStyle('divider');
      $button->setTag('span');
    }

    $this->addElement($button);

    return $button;
  }
}
