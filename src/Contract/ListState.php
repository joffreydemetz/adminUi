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
 * One admin list window : what the filterbar holds, plus where the pager is.
 *
 * The controller builds it from its session filter state, the service unpacks
 * it into whatever scalar arguments its models take — a model never sees a
 * ListState.
 *
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
final class ListState
{
  /**
   * @param array<string,mixed> $filters the extra filterbar selects, by field name
   */
  public function __construct(
    public readonly int $limit = 25,
    public readonly int $offset = 0,
    public readonly string $search = '',
    public readonly string $orderBy = '',
    public readonly array $filters = [],
  ) {}

  /**
   * From the App\Admin\Filters state : search / orderBy / limit are the known
   * keys, everything else the site allow-listed is an extra filter.
   *
   * @param array<string,mixed> $state
   */
  public static function fromFilters(array $state, int $offset = 0): static
  {
    $filters = $state;
    unset($filters['search'], $filters['orderBy'], $filters['limit']);

    return new static(
      \max(0, (int) ($state['limit'] ?? 25)),
      \max(0, $offset),
      (string) ($state['search'] ?? ''),
      (string) ($state['orderBy'] ?? ''),
      $filters,
    );
  }

  /**
   * An extra filter, cast to the type of its default : `filter('idCat')` gives
   * an int, `filter('kind', '')` a string.
   */
  public function filter(string $name, int|string $default = 0): int|string
  {
    $value = $this->filters[$name] ?? null;
    if (null === $value || '' === $value) {
      return $default;
    }

    return \is_int($default) ? (int) $value : (string) $value;
  }
}
