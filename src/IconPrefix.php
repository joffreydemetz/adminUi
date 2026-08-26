<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\AdminUi;

/**
 * The CSS classes toolbar buttons and row triggers paint their icon with.
 *
 * The package used to hardcode `glyphicons glyphicons-<name>`, which stopped
 * being true once an admin stopped shipping Glyphicons: the saturn sites run a
 * generated `admin-font` subset whose classes are `icon icon-<name>`. The
 * default here is the legacy pair on purpose — the legacy framework portails
 * still paint that vocabulary, and they must not change because a consumer
 * elsewhere did.
 *
 * Set it once, at bootstrap, before any toolbar or list is built:
 *
 *     JDZ\AdminUi\IconPrefix::use('icon icon-');
 *
 * The value is the whole prefix, block class included, so an admin with a
 * different block/per-icon split can express it.
 *
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
final class IconPrefix
{
  private static string $prefix = 'glyphicons glyphicons-';

  public static function use(string $prefix): void
  {
    self::$prefix = $prefix;
  }

  /** The icon class for a bare name ('plus' => 'icon icon-plus'). */
  public static function for(string $name): string
  {
    return self::$prefix . $name;
  }

  /** Back to the packaged default — for tests that change it. */
  public static function reset(): void
  {
    self::$prefix = 'glyphicons glyphicons-';
  }
}
