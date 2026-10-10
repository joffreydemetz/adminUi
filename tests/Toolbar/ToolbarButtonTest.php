<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\AdminUi\Tests\Toolbar;

use JDZ\AdminUi\Toolbar\ToolbarButton;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A toolbar button : `data-task` and `type` are what jizy.admin.js dispatches on.
 */
#[CoversClass(ToolbarButton::class)]
class ToolbarButtonTest extends TestCase
{
  public function testAButtonCarriesItsTaskAndTypeFromItsName(): void
  {
    $this->assertSame([
      'renderer' => 'toolbar.button',
      'attrs' => ['class' => 'btn', 'data-task' => 'save', 'href' => '#'],
      'name' => 'save',
      'tag' => 'a',
      'text' => '',
      'type' => 'save',
    ], (new ToolbarButton('save'))->toData());
  }

  public function testTheTypeCanDifferFromTheTask(): void
  {
    $data = (new ToolbarButton('publish'))->setType('selection')->toData();

    $this->assertSame('selection', $data['type']);
    $this->assertSame('publish', $data['attrs']['data-task']);
  }

  public function testAnEmptyTypeIsLeftOut(): void
  {
    $this->assertArrayNotHasKey('type', (new ToolbarButton('save'))->setType('')->toData());
  }

  public function testADividerSpanSkipsTheLinkValidation(): void
  {
    $data = (new ToolbarButton('sep'))->setType('divider')->setTag('span')->toData();

    $this->assertSame('span', $data['tag']);
    $this->assertArrayNotHasKey('href', $data['attrs']);
  }

  public function testADividerLinkIsStillALink(): void
  {
    $data = (new ToolbarButton('sep'))->setType('divider')->toData();

    $this->assertSame('a', $data['tag']);
    $this->assertSame('#', $data['attrs']['href']);
  }

  #[DataProvider('invalidButtons')]
  public function testAButtonTheRendererCannotDrawThrows(string $name, string $type, string $tag, string $message): void
  {
    $button = (new ToolbarButton($name))->setType($type)->setTag($tag);

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage($message);

    $button->toData();
  }

  public static function invalidButtons(): array
  {
    return [
      'a span that is not a divider' => ['name' => 'save', 'type' => 'save', 'tag' => 'span', 'message' => 'Button tag must be "a" or "button"'],
      'a div divider' => ['name' => 'sep', 'type' => 'divider', 'tag' => 'div', 'message' => 'Button tag must be "a" or "button"'],
      'a nameless button' => ['name' => '', 'type' => 'save', 'tag' => 'a', 'message' => 'Button name is required'],
    ];
  }
}
