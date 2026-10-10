<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\AdminUi\Tests\Controller;

use JDZ\AdminUi\Controller\PsrUploadedFileBridge;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\UploadedFile;
use Symfony\Component\HttpFoundation\File\UploadedFile as HttpFoundationFile;

/**
 * PSR-7 upload in, HttpFoundation upload out : the mime the uploader validates
 * on is the sniffed one, and whatever is handed over must survive move().
 */
#[CoversClass(PsrUploadedFileBridge::class)]
class PsrUploadedFileBridgeTest extends TestCase
{
  private const GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

  private const UNSNIFFABLE = "\x00\x01\x02\x03\xfe\xff\x80\x81";

  private string $dir = '';

  /** @var list<string> temporary copies the bridge spooled */
  private array $spooled = [];

  protected function setUp(): void
  {
    $this->dir = \sys_get_temp_dir() . '/jdz-adminui-bridge-' . \bin2hex(\random_bytes(6));

    \mkdir($this->dir);
  }

  protected function tearDown(): void
  {
    foreach ($this->spooled as $path) {
      if (\is_file($path)) {
        \unlink($path);
      }
    }

    foreach (\glob($this->dir . '/*') ?: [] as $path) {
      \unlink($path);
    }

    \rmdir($this->dir);
  }

  public function testALocalUploadIsHandedOverInPlace(): void
  {
    $path = $this->file(\base64_decode(self::GIF));

    $file = PsrUploadedFileBridge::toHttpFoundation(new UploadedFile($path, 'paysage.gif', 'image/gif', 42, \UPLOAD_ERR_OK));

    $this->assertSame($path, $file->getPathname());
    $this->assertSame('paysage.gif', $file->getClientOriginalName());
    $this->assertSame(\UPLOAD_ERR_OK, $file->getError());
    // Not a genuine PHP upload, so test mode is on — it is what lets move() take it.
    $this->assertTrue($file->isValid());
  }

  #[DataProvider('mimes')]
  public function testTheMimeIsSniffedAndTheDeclaredOneIsALastResort(string $bytes, ?string $declared, string $mime): void
  {
    $file = PsrUploadedFileBridge::toHttpFoundation(new UploadedFile($this->file($bytes), 'paysage.gif', $declared, \strlen($bytes), \UPLOAD_ERR_OK));

    $this->assertSame($mime, $file->getClientMimeType());
  }

  public static function mimes(): array
  {
    return [
      'an image declared as text' => ['bytes' => \base64_decode(self::GIF), 'declared' => 'text/plain', 'mime' => 'image/gif'],
      'a php payload announcing an image' => ['bytes' => '<?php echo 1;', 'declared' => 'image/gif', 'mime' => 'text/x-php'],
      'unsniffable bytes keep the declared mime' => ['bytes' => self::UNSNIFFABLE, 'declared' => 'image/png', 'mime' => 'image/png'],
      'unsniffable bytes and nothing declared' => ['bytes' => self::UNSNIFFABLE, 'declared' => null, 'mime' => 'application/octet-stream'],
    ];
  }

  public function testAFileWithoutAClientNameIsCalledFile(): void
  {
    $file = PsrUploadedFileBridge::toHttpFoundation(new UploadedFile($this->file('x'), null, 'text/plain', 1, \UPLOAD_ERR_OK));

    $this->assertSame('file', $file->getClientOriginalName());
  }

  public function testTheUploadErrorIsHandedOver(): void
  {
    $file = PsrUploadedFileBridge::toHttpFoundation(new UploadedFile($this->file('x'), 'a.gif', 'image/gif', 1, \UPLOAD_ERR_PARTIAL));

    $this->assertSame(\UPLOAD_ERR_PARTIAL, $file->getError());
    $this->assertFalse($file->isValid());
  }

  public function testAStreamOnlyUploadIsSpooledFromItsStart(): void
  {
    $stream = (new StreamFactory())->createStream(\base64_decode(self::GIF));
    // Read to the end, as a validator running before the bridge would leave it.
    $stream->getContents();

    $file = $this->bridge(new UploadedFile($stream, 'paysage.gif', 'text/plain', null, \UPLOAD_ERR_OK));

    $this->assertSame(\realpath(\sys_get_temp_dir()), \realpath(\dirname($file->getPathname())));
    $this->assertSame(\base64_decode(self::GIF), \file_get_contents($file->getPathname()));
    $this->assertSame('image/gif', $file->getClientMimeType());
    $this->assertTrue($file->isValid());
  }

  public function testAStreamWithoutUsableMetadataThatCannotRewindIsSpooledFromWhereItIs(): void
  {
    $stream = $this->createMock(StreamInterface::class);
    $stream->method('getMetadata')->willThrowException(new \RuntimeException('Stream is detached'));
    $stream->method('isSeekable')->willReturn(false);
    $stream->expects($this->never())->method('rewind');
    $stream->method('eof')->willReturnOnConsecutiveCalls(false, false, true);
    $stream->method('read')->willReturnOnConsecutiveCalls('GIF8', '9a');

    $upload = $this->createStub(UploadedFileInterface::class);
    $upload->method('getStream')->willReturn($stream);
    $upload->method('getClientFilename')->willReturn('paysage.gif');
    $upload->method('getClientMediaType')->willReturn('image/gif');
    $upload->method('getError')->willReturn(\UPLOAD_ERR_OK);

    $file = $this->bridge($upload);

    $this->assertSame('GIF89a', \file_get_contents($file->getPathname()));
    $this->assertSame('paysage.gif', $file->getClientOriginalName());
  }

  /**
   * tempnam() only fails when the temporary directory itself is unusable, and
   * PHP decides that directory once per process : the bridge runs in a child
   * PHP whose `sys_temp_dir` does not exist.
   */
  public function testASpoolThatCannotCreateItsTemporaryFileThrows(): void
  {
    $code = 'require ' . \var_export(\dirname(__DIR__, 2) . '/vendor/autoload.php', true) . ';'
      . '$stream = (new \Slim\Psr7\Factory\StreamFactory())->createStream("GIF89a");'
      . '$file = new \Slim\Psr7\UploadedFile($stream, "paysage.gif", "image/gif", 6, \UPLOAD_ERR_OK);'
      . 'try { \JDZ\AdminUi\Controller\PsrUploadedFileBridge::toHttpFoundation($file); echo "null"; }'
      . 'catch (\Throwable $e) { echo \json_encode([$e::class, $e->getMessage()]); }';

    $process = \proc_open(
      [\PHP_BINARY, '-n', '-d', 'sys_temp_dir=' . $this->dir . '/missing', '-d', 'display_errors=stderr', '-r', $code],
      [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
      $pipes,
    );
    $out = (string)\stream_get_contents($pipes[1]);
    \stream_get_contents($pipes[2]);
    \fclose($pipes[1]);
    \fclose($pipes[2]);

    $this->assertSame(0, \proc_close($process));
    $this->assertSame(['RuntimeException', 'Could not create a temporary file for the upload'], \json_decode($out, true));
  }

  private function file(string $contents): string
  {
    $path = $this->dir . '/upload-' . \count(\glob($this->dir . '/*') ?: []) . '.tmp';

    \file_put_contents($path, $contents);

    return $path;
  }

  private function bridge(UploadedFileInterface $upload): HttpFoundationFile
  {
    $file = PsrUploadedFileBridge::toHttpFoundation($upload);

    $this->spooled[] = $file->getPathname();

    return $file;
  }
}
