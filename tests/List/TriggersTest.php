<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\AdminUi\Tests\List;

use JDZ\AdminUi\IconPrefix;
use JDZ\AdminUi\List\Trigger;
use JDZ\AdminUi\List\Triggers;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The per-row anchors (edit / publish / trash …) : the trigger type is the
 * class admin.js binds the row action to.
 */
#[CoversClass(Trigger::class)]
#[CoversClass(Triggers::class)]
class TriggersTest extends TestCase
{
  protected function tearDown(): void
  {
    IconPrefix::reset();
  }

  #[DataProvider('triggerTypes')]
  public function testTheTypeBecomesTheTriggerClass(string $name, ?string $type, string $expectedClass): void
  {
    $triggers = new Triggers();
    $triggers->getItemTrigger($name, $type);

    $this->assertSame(['class' => $expectedClass, 'href' => '#'], $triggers->toData()['triggers'][0]['attrs']);
  }

  public static function triggerTypes(): array
  {
    return [
      'type given' => ['name' => 'delete', 'type' => 'trash', 'expectedClass' => 'trash'],
      'no type, the name' => ['name' => 'edit', 'type' => null, 'expectedClass' => 'edit'],
      'empty type, the name' => ['name' => 'edit', 'type' => '', 'expectedClass' => 'edit'],
      'a published toggle' => ['name' => 'published', 'type' => 'unpublish', 'expectedClass' => 'unpublish'],
    ];
  }

  public function testATriggerWithoutTypeHasNoClass(): void
  {
    $this->assertSame(['href' => '#'], (new Trigger('edit'))->toData()['attrs']);
  }

  public function testTheTypeClassComesAfterTheOwnClassesAndOnlyOnce(): void
  {
    $trigger = (new Trigger('edit'))->setType('edit');
    $trigger->addStyle('btn-xs');

    $trigger->toData();

    $this->assertSame('btn-xs edit', $trigger->toData()['attrs']['class']);
  }

  public function testATriggerRendersAsAnAnchorWithItsIcon(): void
  {
    $triggers = new Triggers();
    $triggers->getItemTrigger('edit', null, 'pencil')->setHref('/admin/things/edit/?id=3');

    $this->assertSame([
      'renderer' => 'children',
      'attrs' => [],
      'triggers' => [
        [
          'renderer' => 'button',
          'attrs' => ['class' => 'edit', 'href' => '/admin/things/edit/?id=3'],
          'position' => 1,
          'name' => 'edit',
          'tag' => 'a',
          'text' => '<span class="glyphicons glyphicons-pencil"></span> ',
        ],
      ],
    ], $triggers->toData());
  }

  public function testTheIconFollowsTheIconPrefix(): void
  {
    IconPrefix::use('icon icon-');

    $triggers = new Triggers();
    $triggers->getItemTrigger('trash', null, 'bin');

    $this->assertSame('<span class="icon icon-bin"></span> ', $triggers->toData()['triggers'][0]['text']);
  }

  public function testAskingForAnExistingTriggerReturnsItUnchanged(): void
  {
    $triggers = new Triggers();
    $first = $triggers->getItemTrigger('edit', 'edit', 'pencil');

    $this->assertSame($first, $triggers->getItemTrigger('edit', 'other', 'bin'));

    $data = $triggers->toData()['triggers'];
    $this->assertCount(1, $data);
    $this->assertSame('edit', $data[0]['attrs']['class']);
    $this->assertSame('<span class="glyphicons glyphicons-pencil"></span> ', $data[0]['text']);
  }

  public function testTriggersRenderInTheOrderTheyWereAsked(): void
  {
    $triggers = new Triggers();
    $triggers->getItemTrigger('edit');
    $triggers->getItemTrigger('publish');
    $triggers->getItemTrigger('trash');

    $this->assertSame(['edit', 'publish', 'trash'], \array_column($triggers->toData()['triggers'], 'name'));
  }
}
