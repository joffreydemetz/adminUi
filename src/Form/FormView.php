<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\AdminUi\Form;

use JDZ\Form\Contract\FormInterface;
use JDZ\Renderer\Element;
use JDZ\Renderer\ElementsTrait;

/**
 * FormView wraps a JDZ Form for admin page display with fieldset accordion panel states.
 * 
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class FormView extends Element
{
  use ElementsTrait;

  protected string $renderer = 'form.view';
  
  private FormInterface $form;
  private array $panelStates = [];

  public function __construct(string $name)
  {
    $this->name = $name;
  }

  /**
   * Set the form to wrap
   */
  public function setForm(FormInterface $form): static
  {
    $this->form = $form;
    return $this;
  }

  /**
   * Get the wrapped form
   */
  public function getForm(): FormInterface
  {
    return $this->form;
  }

  public function getRenderer(): string
  {
    return $this->renderer;
  }

  /**
   * Compute and set panel states for all fieldsets
   * This moves the accordion logic from item.tmpl into PHP
   */
  public function computePanelStates(): static
  {
    $formData = $this->form->toData();
    $fieldsets = $formData['fieldsets'] ?? [];
    
    $firstLabeledIndex = null;
    $hasLabeled = false;
    
    foreach ($fieldsets as $index => $fieldset) {
      if (!empty($fieldset['label'])) {
        $hasLabeled = true;
        if ($firstLabeledIndex === null) {
          $firstLabeledIndex = $index;
        }
      }
    }
    
    $this->panelStates = [];
    foreach ($fieldsets as $index => $fieldset) {
      if (empty($fieldset['label'])) {
        // Unlabeled fieldsets are always "force" (open)
        $this->panelStates[$fieldset['name']] = 'force';
      } elseif ($index === $firstLabeledIndex) {
        // First labeled fieldset is "active" (open)
        $this->panelStates[$fieldset['name']] = 'active';
      } else {
        // Other labeled fieldsets are "closed" (collapsed)
        $this->panelStates[$fieldset['name']] = 'closed';
      }
    }
    
    return $this;
  }

  /**
   * Set panel state for a specific fieldset
   */
  public function setPanelState(string $fieldsetName, string $state): static
  {
    $this->panelStates[$fieldsetName] = $state;
    return $this;
  }

  /**
   * Get panel state for a specific fieldset
   */
  public function getPanelState(string $fieldsetName): ?string
  {
    return $this->panelStates[$fieldsetName] ?? null;
  }

  /**
   * Get all panel states
   */
  public function getPanelStates(): array
  {
    return $this->panelStates;
  }

  public function toData(): array
  {
    $data = parent::toData();

    $data['form'] = $this->form->toData();
    $data['panels'] = $this->computePanelStatesForData();

    return $data;
  }

  /**
   * Compute panel states for the toData output
   */
  private function computePanelStatesForData(): array
  {
    if (empty($this->panelStates)) {
      $this->computePanelStates();
    }
    
    $formData = $this->form->toData();
    $fieldsets = $formData['fieldsets'] ?? [];
    $panels = [];
    
    foreach ($fieldsets as $fieldset) {
      $panels[] = [
        'name' => $fieldset['name'],
        'label' => $fieldset['label'] ?? '',
        'description' => $fieldset['description'] ?? '',
        'state' => $this->panelStates[$fieldset['name']] ?? 'closed'
      ];
    }
    
    return $panels;
  }
}