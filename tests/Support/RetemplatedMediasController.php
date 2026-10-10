<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\AdminUi\Tests\Support;

/**
 * A site that names its library screens its own way. Same seam as
 * {@see \JDZ\AdminUi\Controller\Traits\AdminControllerTrait::adminViewName()},
 * owned here because a medias controller stands alone.
 */
final class RetemplatedMediasController extends TestMediasController
{
  protected function mediaView(string $name): string
  {
    return 'views/medias/' . $name . '.twig';
  }
}
