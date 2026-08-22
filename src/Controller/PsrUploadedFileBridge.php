<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace JDZ\AdminUi\Controller;

use Psr\Http\Message\UploadedFileInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * PSR-7 uploaded file -> HttpFoundation uploaded file.
 *
 * `jdz/mediamanager` speaks HttpFoundation, a Slim app speaks PSR-7. This is
 * the one real seam between them.
 *
 * Two deliberate choices :
 *
 * - the mime handed over is the one **detected from the bytes**, not the one
 *   the browser declared. The uploader validates on it, and a declared mime is
 *   attacker-controlled ; a `.php` payload announcing `image/png` must not walk
 *   through the extension whitelist.
 * - `test` mode is turned on whenever the temporary file is not a genuine PHP
 *   upload (a spooled stream, a test harness), because `UploadedFile::move()`
 *   otherwise insists on `move_uploaded_file()` and would refuse the file.
 *
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
final class PsrUploadedFileBridge
{
  /**
   * @throws \RuntimeException when the upload cannot be materialized on disk
   */
  public static function toHttpFoundation(UploadedFileInterface $file): UploadedFile
  {
    $path = self::localPath($file);
    $spooled = null === $path;

    if (true === $spooled) {
      $path = self::spool($file);
    }

    // A spooled copy is never an "uploaded file" as far as PHP is concerned,
    // and neither is anything produced by a test harness.
    $test = true === $spooled || false === @\is_uploaded_file($path);

    return new UploadedFile(
      $path,
      (string)($file->getClientFilename() ?? 'file'),
      self::detectMime($path, $file),
      $file->getError(),
      $test,
    );
  }

  /**
   * The temporary path PHP wrote the upload to, when there is one.
   */
  private static function localPath(UploadedFileInterface $file): ?string
  {
    try {
      $uri = $file->getStream()->getMetadata('uri');
    } catch (\Throwable) {
      return null;
    }

    if (false === \is_string($uri) || '' === $uri || false === @\is_file($uri)) {
      return null;
    }

    return $uri;
  }

  /**
   * Write a stream-only upload out to a temporary file.
   */
  private static function spool(UploadedFileInterface $file): string
  {
    $path = \tempnam(\sys_get_temp_dir(), 'jdzmm');

    if (false === $path) {
      throw new \RuntimeException('Could not create a temporary file for the upload');
    }

    $stream = $file->getStream();

    if (true === $stream->isSeekable()) {
      $stream->rewind();
    }

    $handle = @\fopen($path, 'wb');

    if (false === $handle) {
      throw new \RuntimeException('Could not open the temporary upload file for writing');
    }

    while (false === $stream->eof()) {
      \fwrite($handle, $stream->read(8192));
    }

    \fclose($handle);

    return $path;
  }

  /**
   * Content-sniffed mime, with the declared one as a last resort.
   */
  private static function detectMime(string $path, UploadedFileInterface $file): ?string
  {
    $mime = @\mime_content_type($path);

    if (false !== $mime && '' !== $mime && 'application/octet-stream' !== $mime) {
      return $mime;
    }

    return $file->getClientMediaType();
  }
}
