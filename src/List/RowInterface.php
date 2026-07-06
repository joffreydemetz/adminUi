<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\AdminUi\List;

use JDZ\Utils\DataInterface;

/**
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
interface RowInterface extends DataInterface
{
  public function getButtons(): ItemActions;
  public function getTriggers(): Triggers;
  public function isDisabled(): bool;
  public function getDisabledMessage(): string;
  public function disable(string $message = ''): static;
  
  /**
   * Convert the row to an array suitable for list view rendering
   */
  public function toRowData(Columns $columns): array;
}
