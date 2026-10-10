<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\AdminUi\Tests\Controller\Traits;

use JDZ\AdminUi\Controller\Traits\MediasControllerTrait;
use JDZ\MediaManager\ValueObject\MediaConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Slim\Psr7\UploadedFile;

/**
 * The trait against a real (temporary) media library, driven the way
 * mediamanager.js drives it.
 */
#[CoversTrait(MediasControllerTrait::class)]
#[CoversClass(\JDZ\AdminUi\Controller\PsrUploadedFileBridge::class)]
class MediasControllerTraitTest extends TestCase
{
  private string $root = '';
  private TestMediasController $controller;

  protected function setUp(): void
  {
    $this->root = \sys_get_temp_dir() . '/jdz-medias-' . \bin2hex(\random_bytes(6));

    \mkdir($this->root . '/photos', 0777, true);
    \mkdir($this->root . '/systeme', 0777, true);

    // A 1x1 gif, so the listing has a real image to resolve a thumb for.
    \file_put_contents(
      $this->root . '/photos/une-photo.gif',
      \base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'),
    );

    $this->controller = new TestMediasController($this->root);
  }

  protected function tearDown(): void
  {
    $this->removeTree($this->root);
  }

  // ------------------------------------------------------------- listing --

  public function testInitListsTheRoot(): void
  {
    $data = $this->json($this->controller->initFs($this->get(), new Response()));

    $this->assertSame('', $data['mm']['folder']);
    $this->assertSame(['photos', 'systeme'], \array_column($data['filesystem']['folders'], 'name'));
    $this->assertSame([], $data['filesystem']['files']);
    $this->assertSame([['folder' => '', 'title' => 'Medias']], $data['breadcrumbs']);
    $this->assertSame('/admin/json/mediamanager/upload', $data['dz']['url']);
    $this->assertSame('csrf-token', $data['dz']['params']['_csrf']);
  }

  public function testLoadFsEntersAFolderAndResolvesThumbs(): void
  {
    $data = $this->json($this->controller->loadFs($this->get(['folder' => 'photos']), new Response()));

    $this->assertSame('photos', $data['mm']['folder']);
    $this->assertSame(['une-photo.gif'], \array_column($data['filesystem']['files'], 'name'));
    $this->assertSame('thumb:photos/une-photo.gif@150', $data['filesystem']['files'][0]['thumb']);
    $this->assertSame(['Medias', 'photos'], \array_column($data['breadcrumbs'], 'title'));
  }

  public function testTheWireCodecIsDecodedOnTheWayIn(): void
  {
    \mkdir($this->root . '/photos/2026');

    $data = $this->json($this->controller->loadFs($this->get(['folder' => 'photos[-]2026']), new Response()));

    $this->assertSame('photos/2026', $data['mm']['folder']);
  }

  // ------------------------------------------------------------- folders --

  public function testCreateRenameMoveDeleteAFolder(): void
  {
    $this->controller->loadFs($this->get(['folder' => '']), new Response());

    $created = $this->json($this->controller->folderCreate($this->post(['m' => ['folderName' => 'croquis']]), new Response()));
    $this->assertTrue($created['success']);
    $this->assertDirectoryExists($this->root . '/croquis');

    // The browser walks into the new folder before acting on it.
    $this->controller->loadFs($this->get(['folder' => 'croquis']), new Response());

    $renamed = $this->json($this->controller->folderRename($this->post(['m' => ['newName' => 'esquisses']]), new Response()));
    $this->assertTrue($renamed['success']);
    $this->assertSame('esquisses', $renamed['mm']['folder']);
    $this->assertDirectoryExists($this->root . '/esquisses');

    $moved = $this->json($this->controller->folderMove($this->post(['m' => ['newFolder' => 'photos']]), new Response()));
    $this->assertTrue($moved['success']);
    $this->assertSame('photos/esquisses', $moved['mm']['folder']);

    $deleted = $this->json($this->controller->folderDelete($this->get(), new Response()));
    $this->assertTrue($deleted['success']);
    $this->assertSame('photos', $deleted['parent']);
    $this->assertDirectoryDoesNotExist($this->root . '/photos/esquisses');
  }

  public function testTheRootAndSystemFoldersAreStructural(): void
  {
    $this->controller->loadFs($this->get(['folder' => '']), new Response());
    $this->assertSame(
      'MEDIAMANAGER_ERROR_ROOT_CANNOT_BE_CHANGED',
      $this->json($this->controller->folderRenameForm($this->get(), new Response()))['error'],
    );

    $this->controller->loadFs($this->get(['folder' => 'systeme']), new Response());
    $this->assertSame(
      'MEDIAMANAGER_ERROR_ROOT_FOLDERS_CANNOT_BE_CHANGED',
      $this->json($this->controller->folderMoveForm($this->get(), new Response()))['error'],
    );
  }

  public function testAFolderThatIsNotEmptyIsNotDeleted(): void
  {
    $this->controller->loadFs($this->get(['folder' => 'photos']), new Response());

    $this->assertSame(
      'MEDIAMANAGER_ERROR_FOLDER_NOT_EMPTY',
      $this->json($this->controller->folderDelete($this->get(), new Response()))['error'],
    );
  }

  // --------------------------------------------------------------- files --

  public function testFileInfosThenRenameThenDelete(): void
  {
    $this->controller->loadFs($this->get(['folder' => 'photos']), new Response());

    $infos = $this->json($this->controller->fileInfos($this->get(['fileName' => 'une-photo.gif']), new Response()));
    $this->assertSame('une-photo', $infos['finfos']['namenoext']);
    $this->assertSame('gif', $infos['finfos']['ext']);
    $this->assertStringContainsString('une-photo.gif', $infos['content']);
    $this->assertTrue($infos['noheader']);

    // The rename dialog works off the file the details pane selected.
    $form = $this->json($this->controller->fileRenameForm($this->get(), new Response()));
    $this->assertStringContainsString('value="une-photo"', $form['content']);
    $this->assertStringContainsString('csrf-token', $form['content']);

    $renamed = $this->json($this->controller->fileRename($this->post(['m' => ['newName' => 'autre-photo']]), new Response()));
    $this->assertTrue($renamed['success']);
    $this->assertFileExists($this->root . '/photos/autre-photo.gif');

    $deleted = $this->json($this->controller->fileDelete($this->get(['fileName' => 'autre-photo.gif']), new Response()));
    $this->assertTrue($deleted['success']);
    $this->assertFileDoesNotExist($this->root . '/photos/autre-photo.gif');
  }

  public function testFileMoveCarriesTheFileNameFromTheForm(): void
  {
    $this->controller->loadFs($this->get(['folder' => 'photos']), new Response());

    $moved = $this->json($this->controller->fileMove(
      $this->post(['m' => ['fileName' => 'une-photo.gif', 'newFolder' => 'systeme']]),
      new Response(),
    ));

    $this->assertTrue($moved['success']);
    $this->assertSame('systeme', $moved['mm']['folder']);
    $this->assertFileExists($this->root . '/systeme/une-photo.gif');
  }

  public function testWatermarkingRefusesItselfWhenThereIsNoWatermark(): void
  {
    $this->assertSame(
      'MEDIAMANAGER_ERROR_PROTECT_DISABLED',
      $this->json($this->controller->fileProtect($this->get(['fileName' => 'une-photo.gif']), new Response()))['error'],
    );
  }

  // ----------------------------------------------------------- traversal --

  #[DataProvider('hostilePaths')]
  public function testAHostilePathNeverLeavesTheLibrary(string $folder): void
  {
    $data = $this->json($this->controller->loadFs($this->get(['folder' => $folder]), new Response()));

    // An unknown folder falls back to the root rather than reaching outside.
    $this->assertSame([], $data['filesystem']['files']);
    $this->assertSame(['photos', 'systeme'], \array_column($data['filesystem']['folders'], 'name'));
  }

  public static function hostilePaths(): array
  {
    return [
      'parent' => ['../'],
      'deep parent' => ['../../../etc'],
      'encoded parent' => ['%2e%2e%2f'],
      'wire-encoded parent' => ['..[-]..'],
      'absolute' => ['/etc'],
    ];
  }

  public function testAFileNameWithADirectoryPartIsRefused(): void
  {
    $this->controller->loadFs($this->get(['folder' => 'photos']), new Response());

    $this->assertSame(
      'MEDIAMANAGER_ERROR_INVALID_PATH',
      $this->json($this->controller->fileDelete($this->get(['fileName' => '../une-photo.gif']), new Response()))['error'],
    );
    $this->assertFileExists($this->root . '/photos/une-photo.gif');
  }

  public function testTheMediaBaseUrlIsOverridable(): void
  {
    $relocated = new RelocatedMediasController($this->root);

    $data = $this->json($relocated->initFs($this->get(), new Response()));

    $this->assertSame('/bureau/json/mediamanager/upload', $data['dz']['url']);
  }

  public function testTheMediaViewNamesAreOverridable(): void
  {
    $retemplated = new RetemplatedMediasController($this->root);

    $retemplated->selector($this->get(), new Response());

    $this->assertSame(['views/medias/selector.twig'], $retemplated->views);
  }

  public function testTheMediaViewNamesDefaultToTheHistoricalOnes(): void
  {
    $this->controller->selector($this->get(), new Response());

    $this->assertSame(['views/admin/medias/selector.tmpl'], $this->controller->views);
  }

  // -------------------------------------------------------------- upload --

  public function testUploadStoresTheFileAndAnswersWithItsPath(): void
  {
    $data = $this->json($this->controller->upload(
      $this->upload('paysage.gif', 'image/gif', ['folder' => 'photos', 'force' => '0']),
      new Response(),
    ));

    $this->assertSame(200, $data['status']);
    $this->assertSame('photos/paysage.gif', $data['value']);
    $this->assertSame('../media/photos/paysage.gif', $data['url']);
    $this->assertFileExists($this->root . '/photos/paysage.gif');
  }

  public function testUploadRefusesAnExtensionOffTheWhitelist(): void
  {
    // A PHP payload announcing itself as an image: the mime check alone would
    // let it through and it would be STORED as .php inside the web root.
    $response = $this->controller->upload(
      $this->upload('shell.php', 'image/gif', ['folder' => 'photos']),
      new Response(),
    );

    $this->assertSame(403, $response->getStatusCode());
    $this->assertSame(
      'MEDIAMANAGER_ERROR_UPLOAD_FILE_UNAUTH_EXT',
      $this->json($response)['error'],
    );
    $this->assertFileDoesNotExist($this->root . '/photos/shell.php');
  }

  public function testUploadRefusesAMimeThatDoesNotMatchTheBytes(): void
  {
    // Right extension, wrong content — the bridge sniffs the bytes, so the
    // uploader sees text/plain and turns it away.
    $response = $this->controller->upload(
      $this->upload('paysage.gif', 'image/gif', ['folder' => 'photos'], '<?php echo 1;'),
      new Response(),
    );

    $this->assertSame(403, $response->getStatusCode());
    $this->assertFileDoesNotExist($this->root . '/photos/paysage.gif');
  }

  public function testTheCreatedFolderComesBackSoThePickerCanOpenIt(): void
  {
    $this->controller->loadFs($this->get(['folder' => '']), new Response());

    $created = $this->json($this->controller->folderCreate(
      $this->post(['m' => ['folderName' => 'croquis']]),
      new Response(),
    ));

    $this->assertSame('croquis', $created['folder']);
  }

  public function testThePickerCreatesInTheFolderItPostsNotTheSessionOne(): void
  {
    // The session is looking at the root; the picker's own select is on photos.
    $this->controller->loadFs($this->get(['folder' => '']), new Response());

    $created = $this->json($this->controller->folderCreate(
      $this->post(['m' => ['parent' => 'photos', 'folderName' => 'bretagne']]),
      new Response(),
    ));

    $this->assertTrue($created['success']);
    $this->assertSame('photos/bretagne', $created['folder']);
    $this->assertDirectoryExists($this->root . '/photos/bretagne');
    $this->assertDirectoryDoesNotExist($this->root . '/bretagne');
  }

  public function testAnEmptyPostedParentMeansTheRootNotTheSessionFolder(): void
  {
    $this->controller->loadFs($this->get(['folder' => 'photos']), new Response());

    $created = $this->json($this->controller->folderCreate(
      $this->post(['m' => ['parent' => '', 'folderName' => 'divers']]),
      new Response(),
    ));

    $this->assertSame('divers', $created['folder']);
    $this->assertDirectoryExists($this->root . '/divers');
    $this->assertDirectoryDoesNotExist($this->root . '/photos/divers');
  }

  // ------------------------------------------------------------ selector --

  public function testSelectorGroupsImagesPerFolder(): void
  {
    $data = $this->json($this->controller->selector($this->get(), new Response()));

    $this->assertStringContainsString('data-m-value="photos/une-photo.gif"', $data['content']);
    $this->assertStringContainsString('data-m-url="../media/photos/une-photo.gif"', $data['content']);
    $this->assertStringContainsString('data-m-orientation="square"', $data['content']);
    $this->assertSame('image/gif,image/jpeg,.gif,.jpg', $data['dz']['acceptedFiles']);
  }

  public function testSelectorAlwaysCarriesTheFolderList(): void
  {
    // The picker switches folders on its own; it never sees the media manager
    // screen, so the list has to travel with the view.
    $data = $this->json($this->controller->selector($this->get(), new Response()));

    $this->assertStringContainsString('<option value="photos">', $data['content']);
  }

  public function testSelectorIsNotInEditorModeByDefault(): void
  {
    $data = $this->json($this->controller->selector($this->get(), new Response()));

    $this->assertStringContainsString('data-mode=""', $data['content']);
    $this->assertStringNotContainsString('m-editor', $data['content']);
  }

  public function testEditorModeWithNoCurrentFigureRendersNoPrefill(): void
  {
    $data = $this->json($this->controller->selector($this->get(['mode' => 'editor']), new Response()));

    $this->assertStringContainsString('data-mode="editor"', $data['content']);
    $this->assertStringNotContainsString('m-editor', $data['content']);
  }

  public function testEditorModePrefillsTheFigureBeingEdited(): void
  {
    $data = $this->json($this->controller->selector($this->get([
      'mode' => 'editor',
      'src' => 'media/photos/une-photo.gif',
      'alt' => 'Une photo',
      'caption' => 'En Bretagne',
      'align' => 'right',
    ]), new Response()));

    $this->assertStringContainsString('data-src="media/photos/une-photo.gif"', $data['content']);
    $this->assertStringContainsString('data-alt="Une photo"', $data['content']);
    $this->assertStringContainsString('data-caption="En Bretagne"', $data['content']);
    $this->assertStringContainsString('data-align="right"', $data['content']);
  }

  public function testEditorModeRefusesAnAnchoringItDoesNotKnow(): void
  {
    $data = $this->json($this->controller->selector($this->get([
      'mode' => 'editor',
      'src' => 'media/x.gif',
      'align' => 'javascript:alert(1)',
    ]), new Response()));

    $this->assertStringContainsString('data-align=""', $data['content']);
  }

  public function testAnUnknownModeIsNotEditorMode(): void
  {
    $data = $this->json($this->controller->selector($this->get(['mode' => 'nonsense']), new Response()));

    $this->assertStringContainsString('data-mode=""', $data['content']);
  }

  // ------------------------------------------------------------- helpers --

  private function get(array $query = []): ServerRequestInterface
  {
    return (new ServerRequestFactory())
      ->createServerRequest('GET', '/admin/json/mediamanager/fs')
      ->withQueryParams($query);
  }

  private function post(array $body): ServerRequestInterface
  {
    return (new ServerRequestFactory())
      ->createServerRequest('POST', '/admin/json/mediamanager/task')
      ->withParsedBody($body);
  }

  private function upload(string $name, string $mime, array $body, ?string $contents = null): ServerRequestInterface
  {
    $tmp = \tempnam(\sys_get_temp_dir(), 'jdzup');

    \file_put_contents($tmp, $contents ?? \base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));

    return (new ServerRequestFactory())
      ->createServerRequest('POST', '/admin/json/mediamanager/upload')
      ->withParsedBody($body)
      ->withUploadedFiles([
        'fileUploadName' => new UploadedFile($tmp, $name, $mime, \filesize($tmp), \UPLOAD_ERR_OK),
      ]);
  }

  private function json(ResponseInterface $response): array
  {
    $response->getBody()->rewind();

    return (array)\json_decode((string)$response->getBody()->getContents(), true);
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

      \is_dir($child) ? $this->removeTree($child) : @\unlink($child);
    }

    @\rmdir($path);
  }
}

/**
 * The smallest consumer the trait admits : media config, a translator that
 * echoes keys back, an in-memory state bag and stub renderers.
 */
class TestMediasController
{
  use MediasControllerTrait;

  private array $state = [];

  /** Every view name the trait asked for, in order. */
  public array $views = [];

  public function __construct(private string $root) {}

  protected function mediaConfig(): MediaConfig
  {
    return MediaConfig::fromArray([
      'rootPath' => $this->root,
      'systemFolders' => ['systeme'],
      'extsImage' => ['gif', 'jpg'],
      'mimesImage' => ['image/gif', 'image/jpeg'],
      'maxWeightImage' => 2,
      'maxNumFiles' => 0,
      'maxWeightFiles' => 0,
    ]);
  }

  protected function mediaTranslate(string $key, array $params = []): string
  {
    return $key;
  }

  /** Renders just enough of each view to assert the contract on. */
  protected function mediaRenderFragment(string $view, array $vData): string
  {
    $this->views[] = $view;

    if (\str_contains($view, 'selector')) {
      $html = '<div data-mode="' . $vData['mode'] . '">';

      foreach ($vData['folders'] as $one) {
        $html .= '<option value="' . $one['value'] . '">' . $one['name'] . '</option>';
      }

      if (null !== $vData['current']) {
        $html .= '<fieldset class="m-editor"'
          . ' data-src="' . $vData['current']['src'] . '"'
          . ' data-alt="' . $vData['current']['alt'] . '"'
          . ' data-caption="' . $vData['current']['caption'] . '"'
          . ' data-align="' . $vData['current']['align'] . '"></fieldset>';
      }

      foreach ($vData['tree'] as $folder) {
        foreach ($folder->files as $file) {
          $html .= '<a data-m-value="' . $file->value . '" data-m-url="' . $file->url
            . '" data-m-thumb="' . $file->thumb . '" data-m-orientation="' . $file->orientation . '"></a>';
        }
      }

      return $html . '</div>';
    }

    if (\str_contains($view, 'finfos')) {
      return \implode('', $vData['file']['infos']);
    }

    return '<form action="' . ($vData['action'] ?? '') . '">'
      . '<input name="m[newName]" value="' . ($vData['fileName'] ?? $vData['oldName'] ?? '') . '" />'
      . '<input name="_csrf" value="' . $vData['csrf'] . '" />'
      . '</form>';
  }

  protected function mediaRenderPage(ResponseInterface $response, string $view, array $vData): ResponseInterface
  {
    $response->getBody()->write($view);

    return $response;
  }

  protected function mediaCsrfToken(): string
  {
    return 'csrf-token';
  }

  protected function mediaStateLoad(): array
  {
    return $this->state;
  }

  protected function mediaStateSave(array $state): void
  {
    $this->state = $state;
  }

  protected function mediaThumbUrl(string $relPath, int $width): string
  {
    return 'thumb:' . $relPath . '@' . $width;
  }

  protected function mediaSelectorImage(string $relPath, int $width): ?array
  {
    return ['thumb' => 'thumb:' . $relPath . '@' . $width, 'orientation' => 'square'];
  }
}

/**
 * A site whose media manager lives under another prefix: one hook, which the
 * medias controller owns on its own because it does not extend the entity admin.
 */
final class RelocatedMediasController extends TestMediasController
{
  protected function mediaBaseUrl(): string
  {
    return '/bureau';
  }
}

/**
 * A site that names its library screens its own way. Same seam as
 * {@see \JDZ\AdminUi\Controller\Traits\AdminControllerTrait::adminViewName()},
 * owned here because a medias controller stands alone.
 */
final class RetemplatedMediasController extends TestMediasController
{
  protected function mediaView(string $name): string
  {
    return 'views/medias/' . $name . '.twig';
  }
}
