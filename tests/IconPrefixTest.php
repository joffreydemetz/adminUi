<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\AdminUi\Tests;

use JDZ\AdminUi\IconPrefix;
use JDZ\AdminUi\List\Triggers;
use JDZ\AdminUi\Toolbar\AdminToolbar;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IconPrefix::class)]
class IconPrefixTest extends TestCase
{
  protected function tearDown(): void
  {
    IconPrefix::reset();
  }

  /**
   * The default has to stay the legacy pair: legacy framework admins paint that
   * vocabulary and must not change because another consumer moved on.
   */
  public function testDefaultIsTheLegacyGlyphiconsPair(): void
  {
    $this->assertSame('glyphicons glyphicons-plus', IconPrefix::for('plus'));

    $toolbar = new AdminToolbar('toolbar');
    $toolbar->getToolbarButton('add', null, 'plus');

    $this->assertStringContainsString('glyphicons glyphicons-plus', json_encode($toolbar->toData()));
  }

  public function testAnAdminCanUseItsOwnVocabulary(): void
  {
    IconPrefix::use('icon icon-');

    $this->assertSame('icon icon-plus', IconPrefix::for('plus'));

    $toolbar = new AdminToolbar('toolbar');
    $toolbar->getToolbarButton('add', null, 'plus');
    $data = json_encode($toolbar->toData());

    $this->assertStringContainsString('icon icon-plus', $data);
    $this->assertStringNotContainsString('glyphicons', $data);
  }

  /** Row triggers read the same setting as the toolbar. */
  public function testTriggersFollowTheSameSetting(): void
  {
    IconPrefix::use('icon icon-');

    $triggers = new Triggers('triggers');
    $triggers->getItemTrigger('edit', null, 'edit');

    $this->assertStringContainsString('icon icon-edit', json_encode($triggers->toData()));
  }

  public function testResetPutsTheDefaultBack(): void
  {
    IconPrefix::use('icon icon-');
    IconPrefix::reset();

    $this->assertSame('glyphicons glyphicons-plus', IconPrefix::for('plus'));
  }
}
