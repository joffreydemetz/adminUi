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
 * What an admin save answered : the new/edited id, or the field errors that
 * stopped it.
 *
 * The controller only asks isOk() — on a failure it re-renders the posted form
 * with a 422 and flashes the messages joined by a space, which is exactly what
 * the six site controllers did with their `array{errors, id}` return.
 *
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
final class SaveResult
{
  /**
   * @param array<string,string> $errors field name => message, `_` for the whole form
   */
  private function __construct(
    public readonly array $errors,
    public readonly ?int $id,
  ) {}

  public static function ok(int $id): static
  {
    return new static([], $id);
  }

  /**
   * @param array<string,string> $errors
   */
  public static function fail(array $errors, ?int $id = null): static
  {
    return new static($errors, $id);
  }

  public function isOk(): bool
  {
    return [] === $this->errors;
  }
}
