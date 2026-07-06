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
 * ItemSection represents a titled section within an Item (read-only detail view).
 * 
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class ItemSection extends Element
{
  use ElementsTrait;

  protected string $renderer = 'item.section';
  
  private string $title = '';

  public function __construct(string $name, string $title = '')
  {
    $this->name = $name;
    $this->title = $title;
  }

  public function setTitle(string $title): static
  {
    $this->title = $title;
    return $this;
  }

  public function getTitle(): string
  {
    return $this->title;
  }

  public function getRenderer(): string
  {
    return $this->renderer;
  }

  public function toData(): array
  {
    $data = parent::toData();

    $data['title'] = $this->title;
    $data['fields'] = $this->renderElements();

    return $data;
  }

  /**
   * Add a field to this section
   */
  public function addField(string $name, string $type, string $label, mixed $value = null): ItemField
  {
    $field = new ItemField($name, $type, $label, $value);
    $this->addElement($field);
    return $field;
  }

  /**
   * Get a field by name
   */
  public function getField(string $name): ItemField
  {
    return $this->getElement($name);
  }

  /**
   * Check if a field exists
   */
  public function hasField(string $name): bool
  {
    return $this->hasElement($name);
  }
}