<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\AdminUi\Tests\Controller;

use JDZ\AdminUi\Controller\AdminControllerTrait;
use JDZ\AdminUi\Controller\MediasControllerTrait;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;

/**
 * 2.0.0 moved both controller traits into `Controller\Traits\`. The old names
 * survive through 2.x (removed in 3.0.0) as deprecated traits composing the new
 * ones, so an upgrade does not have to be atomic — this is the proof they still
 * compose, abstract requirements included.
 */
#[CoversTrait(AdminControllerTrait::class)]
#[CoversTrait(MediasControllerTrait::class)]
class DeprecatedTraitAliasesTest extends TestCase
{
  public function testTheOldTraitNamesStillResolve(): void
  {
    $this->assertTrue(\trait_exists(AdminControllerTrait::class));
    $this->assertTrue(\trait_exists(MediasControllerTrait::class));
  }

  public function testTheOldNameCarriesTheTasksFromTheNewTrait(): void
  {
    $class = new \ReflectionClass(LegacyNamedController::class);

    $this->assertTrue($class->hasMethod('list'));
    $this->assertTrue($class->hasMethod('changeorder'));

    // Composed, not copied: the code behind the old name is the moved file.
    $this->assertStringEndsWith(
      'src/Controller/Traits/AdminControllerTrait.php',
      \str_replace('\\', '/', (string) $class->getMethod('adminBaseUrl')->getFileName()),
    );
  }

  public function testTheOldNameStillCarriesItsAbstractRequirements(): void
  {
    $class = new \ReflectionClass(LegacyNamedController::class);

    // service()/buildRow()/buildForm()… stay the consumer's job.
    $this->assertNotSame([], \array_map(
      static fn(\ReflectionMethod $m): string => $m->getName(),
      $class->getMethods(\ReflectionMethod::IS_ABSTRACT),
    ));
  }
}

/** A consumer still writing the pre-2.0.0 `use` line. */
abstract class LegacyNamedController
{
  use AdminControllerTrait;

  protected function component(): string
  {
    return 'things';
  }

  protected function title(): string
  {
    return 'Choses';
  }
}
