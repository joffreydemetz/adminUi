<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\AdminUi\Item;

use JDZ\Renderer\Element;
use JDZ\Renderer\ElementsTrait;

/**
 * Item represents a read-only detail view for admin previews.
 * 
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class Item extends Element
{
  use ElementsTrait;

  protected string $renderer = 'item';

  public function __construct(string $name)
  {
    $this->name = $name;
  }

  public function getRenderer(): string
  {
    return $this->renderer;
  }

  public function toData(): array
  {
    $data = parent::toData();

    $data['sections'] = $this->renderElements();

    return $data;
  }

  /**
   * Add a section to this item
   */
  public function addSection(string $name, string $title = ''): ItemSection
  {
    $section = new ItemSection($name, $title);
    $this->addElement($section);
    return $section;
  }

  /**
   * Get a section by name
   */
  public function getSection(string $name): ItemSection
  {
    return $this->getElement($name);
  }

  /**
   * Check if a section exists
   */
  public function hasSection(string $name): bool
  {
    return $this->hasElement($name);
  }
}