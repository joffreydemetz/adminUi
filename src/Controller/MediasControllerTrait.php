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
 * @deprecated 2.0.0 use {@see Traits\MediasControllerTrait}. Kept through 2.x
 *             so a consumer can move its `use` lines after the upgrade, not
 *             during it; removed in 3.0.0.
 */
trait MediasControllerTrait
{
  use Traits\MediasControllerTrait;
}
