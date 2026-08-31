<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace JDZ\AdminUi\Controller;

/**
 * Back-compatible name for the trait that moved in 2.0.0.
 *
 * @deprecated 2.0.0 use {@see Traits\AdminControllerTrait}. Kept for one minor
 *             so a consumer can move its `use` lines after the upgrade, not
 *             during it; removed in 2.1.0.
 */
trait AdminControllerTrait
{
  use Traits\AdminControllerTrait;
}
