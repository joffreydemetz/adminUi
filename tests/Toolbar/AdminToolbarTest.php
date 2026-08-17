<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\AdminUi\Tests\Toolbar;

use JDZ\AdminUi\Toolbar\AdminToolbar;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JDZ\AdminUi\Toolbar\AdminToolbar
 */
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
}
