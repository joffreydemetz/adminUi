<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\AdminUi\Tests\Controller\Traits;

use JDZ\AdminUi\Controller\Traits\MediasControllerTrait;
use JDZ\AdminUi\Tests\Support\MemoryStreamWrapper;
use JDZ\AdminUi\Tests\Support\TestMediasController;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

/**
 * GET /admin/medias/download — the one task that hands raw bytes to the
 * browser. The folder comes from the session, the name from the query string :
 * neither may reach outside the library, and the name must not reach the
 * response headers as it came.
 *
 * Most cases run on a real temporary library. The names no Windows volume can
 * hold (quotes, line breaks) and the read failures a disk will not produce on
 * demand run on the in-memory `jdzmem://` library.
 */
#[CoversTrait(MediasControllerTrait::class)]
class MediasFileDownloadTest extends TestCase
{
  private const GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

  private const SAFETY_HEADERS = ['X-Content-Type-Options' => ['nosniff']];

  private string $base = '';
  private string $root = '';
  private TestMediasController $controller;

  protected function setUp(): void
  {
    // base/media is the library ; base/outside.txt and base/media-secret/ sit
    // right next to it, the latter sharing its name as a prefix.
    $this->base = \sys_get_temp_dir() . '/jdz-adminui-download-' . \bin2hex(\random_bytes(6));
    $this->root = $this->base . '/media';

    \mkdir($this->root . '/photos/sous-dossier', 0777, true);
    \mkdir($this->base . '/media-secret');

    \file_put_contents($this->root . '/photos/une-photo.gif', \base64_decode(self::GIF));
    \file_put_contents($this->root . '/photos/notes.txt', 'des notes');
    \file_put_contents($this->root . '/photos/couverture.txt', \base64_decode(self::GIF));
    \file_put_contents($this->root . '/photos/photo-été.gif', \base64_decode(self::GIF));
    \file_put_contents($this->root . '/racine.txt', 'à la racine');
    \file_put_contents($this->base . '/outside.txt', 'hors de la médiathèque');
    \file_put_contents($this->base . '/media-secret/secret.txt', 'secret');

    $this->controller = new TestMediasController($this->root);

    MemoryStreamWrapper::register();
  }

  protected function tearDown(): void
  {
    MemoryStreamWrapper::unregister();

    $this->removeTree($this->base);
  }

  // ------------------------------------------------------------ serving --

  public function testAnImageIsStreamedWithItsTypeAndTheSafetyHeaders(): void
  {
    $response = $this->download('photos', 'une-photo.gif');

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame([
      'Content-Type' => ['image/gif'],
      'X-Content-Type-Options' => ['nosniff'],
      'Content-Disposition' => ['inline; filename="une-photo.gif"'],
    ], $response->getHeaders());
    $this->assertSame(\base64_decode(self::GIF), $this->body($response));
  }

  #[DataProvider('sniffedTypes')]
  public function testTheTypeIsReadFromTheBytesNotFromTheName(string $fileName, string $type): void
  {
    $response = $this->download('photos', $fileName);

    $this->assertSame($type, $response->getHeaderLine('Content-Type'));
    $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
  }

  public static function sniffedTypes(): array
  {
    return [
      'a text file' => ['fileName' => 'notes.txt', 'type' => 'text/plain'],
      'gif bytes under a .txt name' => ['fileName' => 'couverture.txt', 'type' => 'image/gif'],
    ];
  }

  public function testALargeFileIsStreamedWhole(): void
  {
    // Several of the 8 KiB chunks the trait reads by.
    $contents = \str_repeat("une ligne de texte\n", 2000);
    \file_put_contents($this->root . '/photos/gros.txt', $contents);

    $response = $this->download('photos', 'gros.txt');

    $this->assertSame(38000, \strlen($this->body($response)));
    $this->assertSame($contents, $this->body($response));
  }

  public function testTheFolderIsTheOneTheSessionIsLookingAt(): void
  {
    $this->assertSame('à la racine', $this->body($this->download('', 'racine.txt')));
    $this->assertSame(404, $this->download('photos', 'racine.txt')->getStatusCode());
  }

  public function testTheDownloadLinkOfTheDetailsPaneServesTheFile(): void
  {
    $this->controller->loadFs($this->get(['folder' => 'photos']), new Response());
    $infos = $this->json($this->controller->fileInfos($this->get(['fileName' => 'photo-été.gif']), new Response()));

    $this->assertSame('/admin/medias/download?fileName=photo-%C3%A9t%C3%A9.gif', $infos['finfos']['download']);

    \parse_str((string)\parse_url($infos['finfos']['download'], \PHP_URL_QUERY), $query);
    $response = $this->controller->fileDownload($this->get($query), new Response());

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame(\base64_decode(self::GIF), $this->body($response));
  }

  // ---------------------------------------------------------- refusals --

  #[DataProvider('notALibraryFile')]
  public function testWhatIsNotAFileOfTheLibraryIs404(string $folder, string $fileName): void
  {
    $response = $this->download(\str_replace('{base}', $this->base, $folder), $fileName);

    $this->assertSame(404, $response->getStatusCode());
    $this->assertSame([], $response->getHeaders());
    $this->assertSame('', $this->body($response));
  }

  /**
   * `folder` is what the browser sends to json/mediamanager/fs (wire form,
   * decoded by the manager) ; `fileName` is the download query string.
   */
  public static function notALibraryFile(): array
  {
    return [
      'a missing file' => ['folder' => 'photos', 'fileName' => 'absente.gif'],
      'no name' => ['folder' => 'photos', 'fileName' => ''],
      'a folder' => ['folder' => '', 'fileName' => 'photos'],
      'a folder inside a folder' => ['folder' => 'photos', 'fileName' => 'sous-dossier'],
      'the current folder' => ['folder' => 'photos', 'fileName' => '.'],
      'the parent folder' => ['folder' => 'photos', 'fileName' => '..'],
      'a directory part, even one staying inside' => ['folder' => 'photos', 'fileName' => '../racine.txt'],
      'traversal in the name' => ['folder' => 'photos', 'fileName' => '../../outside.txt'],
      'backslash traversal in the name' => ['folder' => 'photos', 'fileName' => '..\\..\\outside.txt'],
      'encoded traversal in the name' => ['folder' => 'photos', 'fileName' => '..%2F..%2Foutside.txt'],
      'a NUL byte in the name' => ['folder' => 'photos', 'fileName' => "une-photo.gif\0.txt"],
      'traversal in the folder' => ['folder' => '..', 'fileName' => 'outside.txt'],
      'traversal through a real folder' => ['folder' => 'photos/../..', 'fileName' => 'outside.txt'],
      'wire-encoded traversal in the folder' => ['folder' => 'photos[-]..[-]..', 'fileName' => 'outside.txt'],
      'url-encoded traversal in the folder' => ['folder' => '%2e%2e', 'fileName' => 'outside.txt'],
      'double-encoded traversal in the folder' => ['folder' => '%252e%252e', 'fileName' => 'outside.txt'],
      'backslash traversal in the folder' => ['folder' => '..\\media-secret', 'fileName' => 'secret.txt'],
      'a sibling sharing the library prefix' => ['folder' => '../media-secret', 'fileName' => 'secret.txt'],
      'an encoded sibling' => ['folder' => '..%2fmedia-secret', 'fileName' => 'secret.txt'],
      'an absolute folder' => ['folder' => '{base}', 'fileName' => 'outside.txt'],
      'an absolute sibling folder' => ['folder' => '{base}/media-secret', 'fileName' => 'secret.txt'],
      'a NUL byte in the folder' => ['folder' => "photos\0", 'fileName' => 'une-photo.gif'],
    ];
  }

  public function testAFileThatWillNotOpenIs404(): void
  {
    MemoryStreamWrapper::$files['jdzmem://media/photos/verrou.txt'] = 'verrouillé';
    MemoryStreamWrapper::$unreadable['jdzmem://media/photos/verrou.txt'] = true;

    $response = $this->memoryDownload('verrou.txt');

    $this->assertSame(404, $response->getStatusCode());
    $this->assertSame([], $response->getHeaders());
    $this->assertSame('', $this->body($response));
  }

  // ------------------------------------------------------------ headers --

  #[DataProvider('hostileNames')]
  public function testTheAdvertisedNameLosesItsQuotesAndLineBreaks(string $fileName, string $disposition): void
  {
    MemoryStreamWrapper::$files['jdzmem://media/photos/' . $fileName] = 'contenu';

    $response = $this->memoryDownload($fileName);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame(
      ['Content-Type' => ['text/plain']] + self::SAFETY_HEADERS + ['Content-Disposition' => [$disposition]],
      $response->getHeaders(),
    );
    $this->assertSame('contenu', $this->body($response));
  }

  public static function hostileNames(): array
  {
    return [
      'a double quote' => ['fileName' => 'a"b.txt', 'disposition' => 'inline; filename="ab.txt"'],
      'a quote closing the parameter early' => ['fileName' => 'a"; filename=evil.html', 'disposition' => 'inline; filename="a; filename=evil.html"'],
      'CRLF and a header' => ['fileName' => "x\r\nSet-Cookie: a=1.txt", 'disposition' => 'inline; filename="xSet-Cookie: a=1.txt"'],
      'a lone CR' => ['fileName' => "a\rb.txt", 'disposition' => 'inline; filename="ab.txt"'],
      'a lone LF' => ['fileName' => "a\nb.txt", 'disposition' => 'inline; filename="ab.txt"'],
      'quote and CRLF together' => ['fileName' => "a\"\r\nX-Evil: 1.txt", 'disposition' => 'inline; filename="aX-Evil: 1.txt"'],
      'a semicolon stays inside the quotes' => ['fileName' => 'a;b.txt', 'disposition' => 'inline; filename="a;b.txt"'],
    ];
  }

  /**
   * No RFC 5987 `filename*=UTF-8''…` variant : the UTF-8 bytes go out as they
   * are, which the browsers the back office supports read as UTF-8.
   */
  public function testANonAsciiNameIsAdvertisedAsItsRawBytes(): void
  {
    $response = $this->download('photos', 'photo-été.gif');

    $this->assertSame('inline; filename="photo-été.gif"', $response->getHeaderLine('Content-Disposition'));
  }

  public function testAFileGoneBeforeItsTypeIsReadGoesOutAsOctetStream(): void
  {
    MemoryStreamWrapper::$files['jdzmem://media/photos/fugace.gif'] = \base64_decode(self::GIF);
    MemoryStreamWrapper::$vanishing['jdzmem://media/photos/fugace.gif'] = true;

    $response = $this->memoryDownload('fugace.gif');

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame(
      ['Content-Type' => ['application/octet-stream']] + self::SAFETY_HEADERS + ['Content-Disposition' => ['inline; filename="fugace.gif"']],
      $response->getHeaders(),
    );
    $this->assertSame(\base64_decode(self::GIF), $this->body($response));
  }

  // ------------------------------------------------------------ helpers --

  /** Walk into $folder the way the browser does, then download $fileName from there. */
  private function download(string $folder, string $fileName): ResponseInterface
  {
    $this->controller->loadFs($this->get(['folder' => $folder]), new Response());

    return $this->controller->fileDownload($this->get(['fileName' => $fileName]), new Response());
  }

  /**
   * The in-memory library cannot be listed, so the session is seeded with the
   * folder rather than walked into.
   */
  private function memoryDownload(string $fileName): ResponseInterface
  {
    $controller = new TestMediasController(MemoryStreamWrapper::SCHEME . '://media');
    $controller->state = ['folder' => 'photos'];

    return $controller->fileDownload($this->get(['fileName' => $fileName]), new Response());
  }

  private function get(array $query = []): ServerRequestInterface
  {
    return (new ServerRequestFactory())
      ->createServerRequest('GET', '/admin/medias/download')
      ->withQueryParams($query);
  }

  private function body(ResponseInterface $response): string
  {
    $response->getBody()->rewind();

    return $response->getBody()->getContents();
  }

  private function json(ResponseInterface $response): array
  {
    return (array)\json_decode($this->body($response), true);
  }

  private function removeTree(string $path): void
  {
    if (false === \is_dir($path)) {
      return;
    }

    foreach (\scandir($path) ?: [] as $entry) {
      if ('.' === $entry || '..' === $entry) {
        continue;
      }

      $child = $path . '/' . $entry;

      \is_dir($child) ? $this->removeTree($child) : \unlink($child);
    }

    \rmdir($path);
  }
}
