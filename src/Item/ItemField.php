<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\AdminUi\Item;

use JDZ\Renderer\Element;

/**
 * ItemField represents a single field within an ItemSection for read-only detail views.
 * 
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class ItemField extends Element
{
  protected string $renderer = 'item.field';
  
  private string $type;
  private string $label;
  private mixed $value;
  private bool $emptyHidden = false;

  /**
   * Supported field types
   */
  public const TYPE_TEXT = 'text';
  public const TYPE_BOOL = 'bool';
  public const TYPE_HTML = 'html';
  public const TYPE_URL = 'url';
  public const TYPE_LIST = 'list';
  public const TYPE_JSON = 'json';
  public const TYPE_DATE = 'date';
  public const TYPE_ID = 'id';
  public const TYPE_EMAIL = 'email';
  public const TYPE_PHONE = 'phone';
  public const TYPE_NUMBER = 'number';
  public const TYPE_STRING = 'string';

  public function __construct(string $name, string $type, string $label, mixed $value = null)
  {
    $this->name = $name;
    $this->type = $type;
    $this->label = $label;
    $this->value = $value;
    
    $this->addStyle($type);
    $this->addStyle($name);
  }

  public function setValue(mixed $value): static
  {
    $this->value = $value;
    return $this;
  }

  public function getValue(): mixed
  {
    return $this->value;
  }

  public function setLabel(string $label): static
  {
    $this->label = $label;
    return $this;
  }

  public function getLabel(): string
  {
    return $this->label;
  }

  public function setType(string $type): static
  {
    $this->type = $type;
    return $this;
  }

  public function getType(): string
  {
    return $this->type;
  }

  public function getRenderer(): string
  {
    return $this->renderer;
  }

  public function withEmptyHidden(bool $emptyHidden = true): static
  {
    $this->emptyHidden = $emptyHidden;
    return $this;
  }

  public function isEmptyHidden(): bool
  {
    return $this->emptyHidden;
  }

  public function toData(): array
  {
    $data = parent::toData();

    $data['name'] = $this->name;
    $data['type'] = $this->type;
    $data['label'] = $this->label;
    $data['value'] = $this->value;
    
    if ($this->emptyHidden) {
      $data['hidden'] = $this->isEmpty();
    }

    return $data;
  }

  /**
   * Check if the field value is empty
   */
  private function isEmpty(): bool
  {
    if ($this->value === null) {
      return true;
    }
    
    if (is_string($this->value) && trim($this->value) === '') {
      return true;
    }
    
    if (is_array($this->value) && empty($this->value)) {
      return true;
    }
    
    // For boolean fields, never hide (as per the requirement in jdz-adminui.md)
    if ($this->type === self::TYPE_BOOL) {
      return false;
    }
    
    return false;
  }
}