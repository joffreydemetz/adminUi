<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\AdminUi\Tests\Toolbar;

use JDZ\AdminUi\Toolbar\AdminToolbar;
use JDZ\AdminUi\Toolbar\Toolbar;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AdminToolbar::class)]
#[CoversClass(Toolbar::class)]
class AdminToolbarTest extends TestCase
{
  public function testTitleAndSubtitle(): void
  {
    $toolbar = new AdminToolbar('toolbar');
    $toolbar->setTitle('Title');
    $toolbar->setSubtitle('Subtitle');

    $data = $toolbar->toData();

    $this->assertSame('Title', $data['title']);
    $this->assertSame('Subtitle', $data['subtitle']);
  }

  public function testDividerBetweenButtonsSurvives(): void
  {
    $toolbar = new AdminToolbar('toolbar');
    $toolbar->getToolbarButton('add');
    $toolbar->getToolbarButton('beforeTrash', 'divider');
    $toolbar->getToolbarButton('trash');

    $data = $toolbar->toData();

    $this->assertCount(3, $data['buttons']);
  }

  public function testLeadingDividerIsStripped(): void
  {
    $toolbar = new AdminToolbar('toolbar');
    $toolbar->getToolbarButton('beforeAdd', 'divider');
    $toolbar->getToolbarButton('add');

    $data = $toolbar->toData();

    $this->assertCount(1, $data['buttons']);
  }

  public function testTrailingDividerIsStripped(): void
  {
    $toolbar = new AdminToolbar('toolbar');
    $toolbar->getToolbarButton('add');
    $toolbar->getToolbarButton('beforeTrash', 'divider');

    $data = $toolbar->toData();

    $this->assertCount(1, $data['buttons']);
  }

  public function testDoubledDividersCollapse(): void
  {
    $toolbar = new AdminToolbar('toolbar');
    $toolbar->getToolbarButton('add');
    $toolbar->getToolbarButton('beforePublish', 'divider');
    $toolbar->getToolbarButton('beforeTrash', 'divider');
    $toolbar->getToolbarButton('trash');

    $data = $toolbar->toData();

    $this->assertCount(3, $data['buttons']);
  }

  public function testDividerOnlyToolbarEmpties(): void
  {
    $toolbar = new AdminToolbar('toolbar');
    $toolbar->getToolbarButton('beforeAdd', 'divider');
    $toolbar->getToolbarButton('beforeTrash', 'divider');

    $data = $toolbar->toData();

    $this->assertCount(0, $data['buttons']);
  }

  public function testAButtonIsALinkWithItsIconAndType(): void
  {
    $toolbar = new AdminToolbar('things');
    $toolbar->getToolbarButton('publish', 'selection', 'ok');

    $this->assertSame([
      'renderer' => 'toolbar.button',
      'attrs' => ['class' => 'btn', 'data-task' => 'publish', 'href' => '#'],
      'position' => 1,
      'name' => 'publish',
      'tag' => 'a',
      'text' => '<span class="glyphicons glyphicons-ok"></span> ',
      'type' => 'selection',
    ], $toolbar->toData()['buttons'][0]);
  }

  public function testADividerIsABareSpan(): void
  {
    $toolbar = new AdminToolbar('things');
    $toolbar->getToolbarButton('add');
    $toolbar->getToolbarButton('beforeTrash', 'divider');
    $toolbar->getToolbarButton('trash');

    $this->assertSame([
      'renderer' => 'toolbar.button',
      'attrs' => ['class' => 'divider', 'data-task' => 'beforeTrash'],
      'position' => 2,
      'name' => 'beforeTrash',
      'tag' => 'span',
      'text' => '',
      'type' => 'divider',
    ], $toolbar->toData()['buttons'][1]);
  }

  public function testTheToolbarCarriesItsOwnClass(): void
  {
    $data = (new AdminToolbar('things'))->toData();

    $this->assertSame(['renderer' => 'toolbar', 'attrs' => ['class' => 'ca-toolbar'], 'buttons' => [], 'title' => '', 'subtitle' => ''], $data);
  }

  public function testAskingForAnExistingButtonReturnsItUnchanged(): void
  {
    $toolbar = new AdminToolbar('things');
    $first = $toolbar->getToolbarButton('add', null, 'plus');

    $this->assertSame($first, $toolbar->getToolbarButton('add', 'divider', 'bin'));

    $buttons = $toolbar->toData()['buttons'];
    $this->assertCount(1, $buttons);
    $this->assertSame(['a', 'add'], [$buttons[0]['tag'], $buttons[0]['type']]);
  }

  public function testAddButtonRegistersAPlainButton(): void
  {
    $toolbar = new Toolbar('plain');
    $toolbar->addButton('save')->setHref('/save');

    $this->assertSame([
      'renderer' => 'toolbar',
      'attrs' => [],
      'buttons' => [
        [
          'renderer' => 'toolbar.button',
          'attrs' => ['class' => 'btn', 'data-task' => 'save', 'href' => '/save'],
          'position' => 1,
          'name' => 'save',
          'tag' => 'a',
          'text' => '',
          'type' => 'save',
        ],
      ],
    ], $toolbar->toData());
  }

  public function testAddButtonRefusesADuplicateName(): void
  {
    $toolbar = new Toolbar('plain');
    $toolbar->addButton('save');

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Element save already exists');

    $toolbar->addButton('save');
  }
}
