<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\AdminUi\Tests\List;

use JDZ\AdminUi\List\ItemActions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The per-row action buttons, as items.tmpl renders them.
 */
#[CoversClass(ItemActions::class)]
class ItemActionsTest extends TestCase
{
  public function testAnItemActionIsALinkButton(): void
  {
    $actions = new ItemActions();
    $actions->getItemAction('edit', '/admin/things/edit/?id=3', 'primary', 'pencil', 'Modifier');

    $this->assertSame([
      'renderer' => 'itemActions',
      'attrs' => [],
      'buttons' => [
        [
          'renderer' => 'button',
          'attrs' => ['class' => 'btn btn-primary', 'href' => '/admin/things/edit/?id=3'],
          'position' => 1,
          'name' => 'edit',
          'tag' => 'a',
          'text' => '<span class="glyphicons glyphicons-pencil"></span> Modifier',
        ],
      ],
    ], $actions->toData());
  }

  public function testAnItemActionDefaultsToTheDefaultStyleWithNoIconNorText(): void
  {
    $actions = new ItemActions();
    $actions->getItemAction('view', '/x');

    $button = $actions->toData()['buttons'][0];

    $this->assertSame(['class' => 'btn btn-default', 'href' => '/x'], $button['attrs']);
    $this->assertSame('', $button['text']);
  }

  public function testAskingForAnExistingItemActionReturnsItUnchanged(): void
  {
    $actions = new ItemActions();
    $first = $actions->getItemAction('edit', '/a', 'primary');

    $this->assertSame($first, $actions->getItemAction('edit', '/b', 'danger'));
    $this->assertSame(['class' => 'btn btn-primary', 'href' => '/a'], $actions->toData()['buttons'][0]['attrs']);
  }

  public function testAddButtonPrefixesItsStyleAndPaintsItsIcon(): void
  {
    $actions = new ItemActions();
    $actions->addButton('save', 'success', 'ok');
    $actions->addButton('plain');

    $buttons = $actions->toData()['buttons'];

    $this->assertSame(['class' => 'btn-success', 'href' => '#'], $buttons[0]['attrs']);
    $this->assertSame('<span class="glyphicons glyphicons-ok"></span> ', $buttons[0]['text']);
    $this->assertSame(['href' => '#'], $buttons[1]['attrs']);
    $this->assertSame('', $buttons[1]['text']);
  }

  public function testAddButtonRefusesADuplicateName(): void
  {
    $actions = new ItemActions();
    $actions->addButton('save');

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Element save already exists');

    $actions->addButton('save');
  }
}
