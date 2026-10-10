<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\AdminUi\Tests\Support;

/**
 * A site whose media manager lives under another prefix: one hook, which the
 * medias controller owns on its own because it does not extend the entity admin.
 */
final class RelocatedMediasController extends TestMediasController
{
  protected function mediaBaseUrl(): string
  {
    return '/bureau';
  }
}
