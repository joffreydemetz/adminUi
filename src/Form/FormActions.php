<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\AdminUi\Form;

use JDZ\AdminUi\Toolbar\AdminToolbar;
use JDZ\AdminUi\Toolbar\ToolbarButton;

/**
 * FormActions provides preset toolbar buttons for standard form actions.
 * Replaces the imperative button building in AdminModelItemTrait::onItemToolbar().
 * 
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class FormActions
{
  private AdminToolbar $toolbar;

  public function __construct(AdminToolbar $toolbar)
  {
    $this->toolbar = $toolbar;
  }

  /**
   * Create standard form actions: save, apply, divider, cancel/close
   * This matches the behavior from AdminModelItemTrait::onItemToolbar()
   */
  public function createStandardActions(string $component, bool $isUpdate = false): array
  {
    $buttons = [];
    
    // Save button
    $saveButton = $this->toolbar->getToolbarButton('save', null, 'ok-circle');
    $saveButton->setTip('SAVE_AND_CLOSE'); // Translation will be handled by caller
    $saveButton->setHref("/admin/{$component}/save"); // URL will be built by caller
    $saveButton->addStyle('btn-lg');
    $buttons['save'] = $saveButton;
    
    // Apply button
    $applyButton = $this->toolbar->getToolbarButton('apply', null, 'disk-saved');
    $applyButton->setTip('SAVE'); // Translation will be handled by caller
    $applyButton->setHref("/admin/{$component}/apply"); // URL will be built by caller
    $applyButton->addStyle('btn-lg');
    $buttons['apply'] = $applyButton;
    
    // Divider
    $divider = $this->toolbar->getToolbarButton('beforeCancel', 'divider');
    $buttons['divider'] = $divider;
    
    // Cancel/Close button
    $cancelButton = $this->toolbar->getToolbarButton('cancel', null, 'remove-circle');
    $cancelButton->setHref("/admin/{$component}/cancel"); // URL will be built by caller
    $cancelButton->addStyle('btn-lg');
    
    if ($isUpdate) {
      $cancelButton->setTip('CANCEL'); // Translation will be handled by caller
    } else {
      $cancelButton->setType('close')
        ->setTip('CLOSE') // Translation will be handled by caller
        ->addDataAttr('task', 'close');
    }
    $buttons['cancel'] = $cancelButton;
    
    return $buttons;
  }

  /**
   * Create a save-only action set
   */
  public function createSaveAction(string $component, string $tip = 'SAVE_AND_CLOSE', string $icon = 'ok-circle'): ToolbarButton
  {
    $button = $this->toolbar->getToolbarButton('save', null, $icon);
    $button->setTip($tip);
    $button->setHref("/admin/{$component}/save");
    $button->addStyle('btn-lg');
    
    return $button;
  }

  /**
   * Create a save and apply action set
   */
  public function createSaveAndApplyActions(string $component): array
  {
    return [
      'save' => $this->createSaveAction($component),
      'apply' => $this->createApplyAction($component)
    ];
  }

  /**
   * Create an apply-only action
   */
  public function createApplyAction(string $component, string $tip = 'SAVE', string $icon = 'disk-saved'): ToolbarButton
  {
    $button = $this->toolbar->getToolbarButton('apply', null, $icon);
    $button->setTip($tip);
    $button->setHref("/admin/{$component}/apply");
    $button->addStyle('btn-lg');
    
    return $button;
  }

  /**
   * Create a cancel action (adapts based on context)
   */
  public function createCancelAction(string $component, bool $isUpdate = false, string $cancelTip = 'CANCEL', string $closeTip = 'CLOSE'): ToolbarButton
  {
    $button = $this->toolbar->getToolbarButton('cancel', null, 'remove-circle');
    $button->setHref("/admin/{$component}/cancel");
    $button->addStyle('btn-lg');
    
    if ($isUpdate) {
      $button->setTip($cancelTip);
    } else {
      $button->setType('close')
        ->setTip($closeTip)
        ->addDataAttr('task', 'close');
    }
    
    return $button;
  }

  /**
   * Create a divider button
   */
  public function createDivider(string $name = 'divider'): ToolbarButton
  {
    return $this->toolbar->getToolbarButton($name, 'divider');
  }

  /**
   * Get the toolbar
   */
  public function getToolbar(): AdminToolbar
  {
    return $this->toolbar;
  }
}