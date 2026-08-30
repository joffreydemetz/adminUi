<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace JDZ\AdminUi\Contract;

/**
 * What AdminControllerTrait needs of a component to run its /admin screens.
 *
 * The implementation is the site's service : it owns the validation, the
 * business rules and the model calls, and it is the only layer that knows how
 * a row is stored. The controller never touches a model.
 *
 * Do not inject this interface by its name in a DI container — the controllers
 * hold their concrete service (PHP-DI cannot instantiate an interface).
 *
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
interface AdminServiceInterface
{
  /** Total rows matching the filter state, ignoring the window. */
  public function count(ListState $state): int;

  /** @return array<object> the rows of one list window */
  public function list(ListState $state): array;

  public function find(int $id): ?object;

  /**
   * Validate and persist a posted admin form.
   *
   * @param array<string,mixed> $input the parsed request body
   * @param ?int                $id    null on a create
   */
  public function save(array $input, ?int $id): SaveResult;

  /** @param array<int> $ids */
  public function setPublished(array $ids, int $published): void;

  /** @param array<int> $ids */
  public function delete(array $ids): void;

  public function moveOrdering(int $id, int $newValue): void;
}
