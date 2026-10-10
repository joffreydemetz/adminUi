<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\AdminUi\Tests\Support;

/**
 * A read-only, in-memory `jdzmem://` filesystem.
 *
 * It serves file names no Windows volume can hold (`"`, CR, LF, control
 * characters) and failures a real disk will not produce on demand : a file that
 * stats but will not open, a file that is gone once it has been read.
 * Directories are implied by the files below them.
 */
final class MemoryStreamWrapper
{
  public const SCHEME = 'jdzmem';

  /** @var array<string,string> full path => contents */
  public static array $files = [];

  /** @var array<string,true> paths that stat as files but refuse to open */
  public static array $unreadable = [];

  /** @var array<string,true> paths removed as soon as they are closed */
  public static array $vanishing = [];

  /** @var resource|null set by PHP */
  public $context;

  private string $path = '';
  private string $data = '';
  private int $pos = 0;

  public static function register(): void
  {
    self::$files = [];
    self::$unreadable = [];
    self::$vanishing = [];

    \stream_wrapper_register(self::SCHEME, self::class);
  }

  public static function unregister(): void
  {
    \stream_wrapper_unregister(self::SCHEME);

    self::$files = [];
    self::$unreadable = [];
    self::$vanishing = [];
  }

  public function url_stat(string $path, int $flags): array|false
  {
    $path = \rtrim($path, '/');

    if (isset(self::$files[$path])) {
      return self::stat(0100644, \strlen(self::$files[$path]));
    }

    foreach (\array_keys(self::$files) as $file) {
      if (\str_starts_with($file, $path . '/')) {
        return self::stat(040755, 0);
      }
    }

    return false;
  }

  public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
  {
    if ('rb' !== $mode && 'r' !== $mode) {
      return false;
    }

    if (false === isset(self::$files[$path]) || true === isset(self::$unreadable[$path])) {
      return false;
    }

    $this->path = $path;
    $this->data = self::$files[$path];
    $this->pos = 0;

    return true;
  }

  public function stream_read(int $count): string
  {
    $chunk = \substr($this->data, $this->pos, $count);
    $this->pos += \strlen($chunk);

    return $chunk;
  }

  public function stream_eof(): bool
  {
    return $this->pos >= \strlen($this->data);
  }

  public function stream_tell(): int
  {
    return $this->pos;
  }

  public function stream_seek(int $offset, int $whence): bool
  {
    $target = match ($whence) {
      \SEEK_CUR => $this->pos + $offset,
      \SEEK_END => \strlen($this->data) + $offset,
      default => $offset,
    };

    if ($target < 0) {
      return false;
    }

    $this->pos = $target;

    return true;
  }

  public function stream_stat(): array
  {
    return self::stat(0100644, \strlen($this->data));
  }

  public function stream_close(): void
  {
    if (true === isset(self::$vanishing[$this->path])) {
      unset(self::$files[$this->path], self::$vanishing[$this->path]);
    }
  }

  private static function stat(int $mode, int $size): array
  {
    return [
      'dev' => 0,
      'ino' => 0,
      'mode' => $mode,
      'nlink' => 1,
      'uid' => 0,
      'gid' => 0,
      'rdev' => 0,
      'size' => $size,
      'atime' => 0,
      'mtime' => 0,
      'ctime' => 0,
      'blksize' => -1,
      'blocks' => -1,
    ];
  }
}
