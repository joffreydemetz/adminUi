<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\AdminUi\Tests\Controller\Traits;

use JDZ\AdminUi\Controller\PsrUploadedFileBridge;
use JDZ\AdminUi\Controller\Traits\MediasControllerTrait;
use JDZ\AdminUi\Tests\Support\RelocatedMediasController;
use JDZ\AdminUi\Tests\Support\RetemplatedMediasController;
use JDZ\AdminUi\Tests\Support\TestMediasController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Slim\Psr7\UploadedFile;
use Symfony\Component\Filesystem\Path;

/**
 * The trait against a real (temporary) media library, driven the way
 * mediamanager.js drives it.
 */
#[CoversTrait(MediasControllerTrait::class)]
#[CoversClass(PsrUploadedFileBridge::class)]
class MediasControllerTraitTest extends TestCase
{
  private string $base = '';
  private string $root = '';
  private TestMediasController $controller;

  /** @var list<string> uploads the helper wrote, gone in tearDown whether or not they were moved */
  private array $tempFiles = [];

  protected function setUp(): void
  {
    $this->base = \sys_get_temp_dir() . '/jdz-medias-' . \bin2hex(\random_bytes(6));
    $this->root = $this->base . '/media';

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
    $this->removeTree($this->base);

    foreach ($this->tempFiles as $file) {
      if (\is_file($file)) {
        \unlink($file);
      }
    }
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

  // ---------------------------------------------------------------- page --

  public function testDisplayRendersTheBrowserPageWithTheLibraryLimits(): void
  {
    $controller = new TestMediasController($this->root, ['extsDocument' => ['pdf'], 'maxWeightDocument' => 8]);

    $controller->display($this->get(), new Response());

    [$view, $vData] = $controller->page;
    $toolbar = $vData['toolbar'];
    $stats = $vData['stats'];
    unset($vData['toolbar'], $vData['stats']);

    $this->assertSame('views/admin/medias/display.tmpl', $view);
    $this->assertSame([
      'component' => 'medias',
      'showFilterbar' => false,
      'filterbar' => null,
      'authExts' => 'pdf, gif, jpg',
      'authExtsImage' => 'gif, jpg',
      'authExtsDocument' => 'pdf',
      'maxWeightImage' => 2,
      'maxWeightDocument' => 8,
    ], $vData);
    $this->assertSame([true, []], [$stats['uploadable'], $stats['errors']]);
    $this->assertSame(['MEDIAS', ''], [$toolbar['title'], $toolbar['subtitle']]);
  }

  public function testTheToolbarButtonsAreTheTasksMediamanagerJsListensFor(): void
  {
    $this->controller->display($this->get(), new Response());

    $buttons = $this->controller->page[1]['toolbar']['buttons'];

    $this->assertSame(
      ['dataRefresh', null, 'dirCreate', 'dirRename', 'dirMove', null, 'filUpload', null, 'displayThumbs', 'displaySimple'],
      \array_map(static fn(array $button): ?string => $button['attrs']['data-mm-task'] ?? null, $buttons),
    );
    $this->assertSame(
      ['refresh', 'divider', 'new-folder', 'rename-folder', 'move-folder', 'divider', 'new-file', 'divider', 'display-thumbs', 'display-simple'],
      \array_column($buttons, 'type'),
    );
    $this->assertSame('TOOLBAR_MEDIA_NEW_FOLDER', $buttons[2]['attrs']['title']);
  }

  public function testQuotaComplaintsArriveTranslated(): void
  {
    $controller = new TestMediasController($this->root, ['maxNumFiles' => 1]);

    $stats = $this->json($controller->initFs($this->get(), new Response()))['stats'];

    $this->assertFalse($stats['uploadable']);
    $this->assertSame(['MEDIAMANAGER_ERROR_UPLOAD_TOO_MANY_FILES'], $stats['errors']);
    $this->assertContains(['MEDIAMANAGER_ERROR_UPLOAD_TOO_MANY_FILES', ['%maxFileCount%' => 1]], $controller->translations);
  }

  public function testLoadFsRemembersTheDisplayModeAndTheFilter(): void
  {
    $this->controller->loadFs($this->get(['folder' => 'photos', 'display' => 'simple', 'only' => 'images']), new Response());

    // A refresh carries no parameter: the session is what it reloads from.
    $data = $this->json($this->controller->loadFs($this->get(), new Response()));

    $this->assertSame(['folder' => 'photos', 'display' => 'simple', 'only' => 'images', 'file' => ''], $data['mm']);
    $this->assertSame($data['mm'], $this->controller->state);
  }

  // ------------------------------------------------------------- dialogs --

  public function testFolderCreateFormIsAModalPostingBackToTheCreateTask(): void
  {
    $data = $this->json($this->controller->folderCreateForm($this->get(), new Response()));

    $this->assertSame([
      'content' => '<form action="/admin/json/mediamanager/folder/create"><input name="m[newName]" value="" /><input name="_csrf" value="csrf-token" /></form>',
      'title' => 'MEDIAMANAGER_DIALOG_CREATE_FOLDER',
      'noheader' => false,
      'closeIcon' => true,
      'size' => 'sm',
    ], $data);
    $this->assertSame(
      [['views/admin/medias/mkdir.tmpl', ['action' => '/admin/json/mediamanager/folder/create', 'csrf' => 'csrf-token']]],
      $this->controller->fragments,
    );
  }

  public function testTheDialogsPostUnderTheRelocatedBaseUrl(): void
  {
    $relocated = new RelocatedMediasController($this->root);

    $relocated->folderCreateForm($this->get(), new Response());

    $this->assertSame('/bureau/json/mediamanager/folder/create', $relocated->fragments[0][1]['action']);
  }

  public function testFolderRenameFormNamesTheFolderBeingRenamed(): void
  {
    \mkdir($this->root . '/photos/2026');
    $this->controller->loadFs($this->get(['folder' => 'photos[-]2026']), new Response());

    $data = $this->json($this->controller->folderRenameForm($this->get(), new Response()));

    $this->assertSame('MEDIAMANAGER_DIALOG_RENAME_FOLDER', $data['title']);
    $this->assertSame(
      ['views/admin/medias/renamedir.tmpl', [
        'action' => '/admin/json/mediamanager/folder/rename',
        'oldFolder' => 'photos/2026',
        'oldName' => '2026',
        'csrf' => 'csrf-token',
      ]],
      \end($this->controller->fragments),
    );
  }

  public function testFolderMoveFormOffersTheFolderTree(): void
  {
    $this->controller->loadFs($this->get(['folder' => 'photos']), new Response());

    $data = $this->json($this->controller->folderMoveForm($this->get(), new Response()));

    $this->assertSame('MEDIAMANAGER_DIALOG_MOVE_FOLDER', $data['title']);
    $this->assertSame(
      ['views/admin/medias/movedir.tmpl', [
        'action' => '/admin/json/mediamanager/folder/move',
        'oldFolder' => 'photos',
        'oldName' => 'photos',
        'folderTree' => self::FOLDER_TREE,
        'csrf' => 'csrf-token',
      ]],
      \end($this->controller->fragments),
    );
  }

  public function testFileMoveFormNeedsASelectedFile(): void
  {
    $this->controller->loadFs($this->get(['folder' => 'photos']), new Response());

    $this->assertSame(
      ['error' => 'MEDIAMANAGER_ERROR_NO_FILE_SPECIFIED'],
      $this->json($this->controller->fileMoveForm($this->get(), new Response())),
    );
    $this->assertSame([], $this->controller->fragments);
  }

  public function testFileMoveFormOffersTheFolderTreeForTheSelectedFile(): void
  {
    $this->controller->loadFs($this->get(['folder' => 'photos']), new Response());
    $this->controller->fileInfos($this->get(['fileName' => 'une-photo.gif']), new Response());

    $data = $this->json($this->controller->fileMoveForm($this->get(), new Response()));

    $this->assertSame(['MEDIAMANAGER_DIALOG_MOVE_FILE', false, true, 'sm'], [$data['title'], $data['noheader'], $data['closeIcon'], $data['size']]);
    $this->assertSame(
      ['views/admin/medias/move.tmpl', [
        'action' => '/admin/json/mediamanager/file/move',
        'fileName' => 'une-photo.gif',
        'oldFolder' => 'photos',
        'folderTree' => self::FOLDER_TREE,
        'csrf' => 'csrf-token',
      ]],
      \end($this->controller->fragments),
    );
  }

  // ------------------------------------------------------------ refusals --

  #[DataProvider('refusedOperations')]
  public function testARefusedOperationAnswersItsTranslatedError(string $task, string $folder, array $query, array $posted, string $error): void
  {
    $this->controller->loadFs($this->get(['folder' => $folder]), new Response());

    $request = [] === $posted ? $this->get($query) : $this->post($posted);

    $this->assertSame(['error' => $error], $this->json($this->controller->{$task}($request, new Response())));
    $this->assertSame($folder, $this->controller->state['folder']);
  }

  public static function refusedOperations(): array
  {
    return [
      'a folder without a name' => ['task' => 'folderCreate', 'folder' => 'photos', 'query' => [], 'posted' => ['m' => ['folderName' => '']], 'error' => 'MEDIAMANAGER_ERROR_ENTER_FOLDER_NAME'],
      'renaming the root' => ['task' => 'folderRename', 'folder' => '', 'query' => [], 'posted' => ['m' => ['newName' => 'x']], 'error' => 'MEDIAMANAGER_ERROR_ROOT_CANNOT_BE_CHANGED'],
      'moving the root' => ['task' => 'folderMove', 'folder' => '', 'query' => [], 'posted' => ['m' => ['newFolder' => 'photos']], 'error' => 'MEDIAMANAGER_ERROR_ROOT_CANNOT_BE_CHANGED'],
      'renaming no file' => ['task' => 'fileRename', 'folder' => 'photos', 'query' => [], 'posted' => ['m' => ['newName' => 'x']], 'error' => 'MEDIAMANAGER_ERROR_NO_FILE_SPECIFIED'],
      'moving no file' => ['task' => 'fileMove', 'folder' => 'photos', 'query' => [], 'posted' => ['m' => ['newFolder' => 'systeme']], 'error' => 'MEDIAMANAGER_ERROR_NO_FILE_SPECIFIED'],
      'the rename dialog with no file' => ['task' => 'fileRenameForm', 'folder' => 'photos', 'query' => [], 'posted' => [], 'error' => 'MEDIAMANAGER_ERROR_NO_FILE_SPECIFIED'],
      'details of an unknown file' => ['task' => 'fileInfos', 'folder' => 'photos', 'query' => ['fileName' => 'absente.gif'], 'posted' => [], 'error' => 'MEDIAMANAGER_ERROR_SOURCE_FILE_NOT_FOUND'],
      'deleting an unknown file' => ['task' => 'fileDelete', 'folder' => 'photos', 'query' => ['fileName' => 'absente.gif'], 'posted' => [], 'error' => 'MEDIAMANAGER_ERROR_SOURCE_FILE_NOT_FOUND'],
    ];
  }

  // ----------------------------------------------------------- watermark --

  #[DataProvider('watermarkingOff')]
  public function testTheWatermarkEndpointsRefuseThemselvesWhenWatermarkingIsOff(string $task, array $config): void
  {
    \mkdir($this->protectPath());
    \file_put_contents($this->watermarkPath(), 'png');

    $controller = new TestMediasController($this->root, $this->resolvePlaceholders($config));
    $controller->loadFs($this->get(['folder' => 'photos']), new Response());

    $this->assertSame(
      ['error' => 'MEDIAMANAGER_ERROR_PROTECT_DISABLED'],
      $this->json($controller->{$task}($this->get(['fileName' => 'une-photo.gif']), new Response())),
    );
    $this->assertFileDoesNotExist($this->protectPath() . '/une-photo.gif');
  }

  public static function watermarkingOff(): array
  {
    $rows = [];

    foreach (['fileProtect', 'fileUnprotect'] as $task) {
      $rows[$task . ', nothing configured'] = ['task' => $task, 'config' => []];
      $rows[$task . ', no watermark'] = ['task' => $task, 'config' => ['protectPath' => '{protect}']];
      $rows[$task . ', the watermark file is missing'] = ['task' => $task, 'config' => ['protectPath' => '{protect}', 'watermarkPath' => '{protect}/absent.png']];
      $rows[$task . ', no backup folder'] = ['task' => $task, 'config' => ['watermarkPath' => '{watermark}']];
    }

    return $rows;
  }

  public function testUnprotectRestoresThePristineCopy(): void
  {
    $controller = $this->watermarkingController();
    $pristine = (string)\file_get_contents($this->root . '/photos/une-photo.gif');
    \file_put_contents($this->protectPath() . '/une-photo.gif', $pristine);
    \file_put_contents($this->root . '/photos/une-photo.gif', 'stamped');

    $data = $this->json($controller->fileUnprotect($this->get(['fileName' => 'une-photo.gif']), new Response()));

    $this->assertSame(['success' => true, 'message' => 'MEDIAMANAGER_SUCCESS_UNPROTECT'], $data);
    $this->assertSame($pristine, \file_get_contents($this->root . '/photos/une-photo.gif'));
    $this->assertFileDoesNotExist($this->protectPath() . '/une-photo.gif');
  }

  #[DataProvider('unprotectRefusals')]
  public function testUnprotectRefusals(string $fileName, string $error): void
  {
    $controller = $this->watermarkingController();

    $this->assertSame(
      ['error' => $error],
      $this->json($controller->fileUnprotect($this->get(['fileName' => $fileName]), new Response())),
    );
  }

  public static function unprotectRefusals(): array
  {
    return [
      'never protected' => ['fileName' => 'une-photo.gif', 'error' => 'MEDIAMANAGER_ERROR_PROTECT_ORIGINAL_NOT_FOUND'],
      'not an image' => ['fileName' => 'notes.txt', 'error' => 'MEDIAMANAGER_ERROR_NOT_AN_IMAGE'],
      'a missing file' => ['fileName' => 'absente.gif', 'error' => 'MEDIAMANAGER_ERROR_SOURCE_FILE_NOT_FOUND'],
      'a directory part' => ['fileName' => '../une-photo.gif', 'error' => 'MEDIAMANAGER_ERROR_INVALID_PATH'],
    ];
  }

  #[RequiresPhpExtension('gd')]
  public function testProtectSaysWhetherItRestampedAnAlreadyProtectedImage(): void
  {
    $controller = $this->watermarkingController();

    $first = $this->json($controller->fileProtect($this->get(['fileName' => 'une-photo.gif']), new Response()));
    $second = $this->json($controller->fileProtect($this->get(['fileName' => 'une-photo.gif']), new Response()));

    $this->assertSame(['success' => true, 'updateWatermark' => false, 'message' => 'MEDIAMANAGER_SUCCESS_PROTECT'], $first);
    $this->assertSame(['success' => true, 'updateWatermark' => true, 'message' => 'MEDIAMANAGER_SUCCESS_PROTECT'], $second);
    $this->assertFileExists($this->protectPath() . '/une-photo.gif');
  }

  public function testProtectRefusesWhatIsNotAnImage(): void
  {
    $controller = $this->watermarkingController();

    $this->assertSame(
      ['error' => 'MEDIAMANAGER_ERROR_NOT_AN_IMAGE'],
      $this->json($controller->fileProtect($this->get(['fileName' => 'notes.txt']), new Response())),
    );
  }

  // ----------------------------------------------------- upload refusals --

  #[DataProvider('missingUploads')]
  public function testAnUploadWithoutAUsableFileIsRefused(?int $error): void
  {
    $request = null === $error
      ? $this->post(['folder' => 'photos', 'force' => '1'])
      : $this->upload('paysage.gif', 'image/gif', ['folder' => 'photos', 'force' => '1'], null, $error);

    $response = $this->controller->upload($request, new Response());

    $this->assertSame(403, $response->getStatusCode());
    $this->assertSame(
      ['force' => true, 'folder' => 'photos', 'status' => 403, 'error' => 'MEDIAMANAGER_ERROR_NO_FILE_SPECIFIED'],
      $this->json($response),
    );
  }

  public static function missingUploads(): array
  {
    return [
      'no file field' => ['error' => null],
      'over the ini size' => ['error' => \UPLOAD_ERR_INI_SIZE],
      'partial' => ['error' => \UPLOAD_ERR_PARTIAL],
      'no file chosen' => ['error' => \UPLOAD_ERR_NO_FILE],
    ];
  }

  public function testAnUploadWithoutExtensionIsRefusedWithTheWhitelist(): void
  {
    $response = $this->controller->upload($this->upload('paysage', 'image/gif', ['folder' => 'photos']), new Response());

    $this->assertSame(403, $response->getStatusCode());
    $this->assertSame('MEDIAMANAGER_ERROR_UPLOAD_FILE_UNAUTH_EXT', $this->json($response)['error']);
    $this->assertContains(['MEDIAMANAGER_ERROR_UPLOAD_FILE_UNAUTH_EXT', ['%authExts%' => 'gif, jpg']], $this->controller->translations);
  }

  public function testTheExtensionWhitelistIgnoresCase(): void
  {
    $data = $this->json($this->controller->upload($this->upload('PAYSAGE.GIF', 'image/gif', ['folder' => 'photos']), new Response()));

    $this->assertSame(200, $data['status']);
    $this->assertSame('photos/paysage.gif', $data['value']);
  }

  public function testAnUploadTheLibraryRefusesComesBackTranslated(): void
  {
    $controller = new TestMediasController($this->root, ['maxNumFiles' => 1]);

    $response = $controller->upload($this->upload('paysage.gif', 'image/gif', ['folder' => 'photos']), new Response());

    $this->assertSame(403, $response->getStatusCode());
    $this->assertSame(
      ['force' => false, 'folder' => 'photos', 'status' => 403, 'error' => 'MEDIAMANAGER_ERROR_UPLOAD_TOO_MANY_FILES'],
      $this->json($response),
    );
    $this->assertSame(['MEDIAMANAGER_ERROR_UPLOAD_TOO_MANY_FILES', ['%maxFileCount%' => 1]], \end($controller->translations));
  }

  public function testAnUploadThatBreaksIsAnOperationFailureCarryingTheReason(): void
  {
    $missing = $this->root . '/absent-library';
    $controller = new TestMediasController($missing);

    $response = $controller->upload($this->upload('paysage.gif', 'image/gif', ['folder' => '']), new Response());

    $this->assertSame(403, $response->getStatusCode());
    $this->assertSame('MEDIAMANAGER_ERROR_OPERATION_FAILED', $this->json($response)['error']);
    $this->assertSame(
      ['MEDIAMANAGER_ERROR_OPERATION_FAILED', ['%error%' => 'The "' . Path::canonicalize($missing) . '" directory does not exist.']],
      \end($controller->translations),
    );
  }

  /** The folder tree every move dialog of this fixture library carries. */
  private const FOLDER_TREE = [
    ['value' => '', 'name' => 'Racine', 'level' => 0],
    ['value' => 'photos', 'name' => 'photos', 'level' => 1],
    ['value' => 'systeme', 'name' => 'systeme', 'level' => 1],
  ];

  /** The backup folder, beside the library. */
  private function protectPath(): string
  {
    return $this->base . '/protect';
  }

  private function watermarkPath(): string
  {
    return $this->base . '/watermark.png';
  }

  /** Watermarking on : a backup folder and a real watermark, both beside the library. */
  private function watermarkingController(): TestMediasController
  {
    \mkdir($this->protectPath());
    \file_put_contents($this->watermarkPath(), \base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
    \file_put_contents($this->root . '/photos/notes.txt', 'notes');

    $controller = new TestMediasController($this->root, ['protectPath' => $this->protectPath(), 'watermarkPath' => $this->watermarkPath()]);
    $controller->loadFs($this->get(['folder' => 'photos']), new Response());

    return $controller;
  }

  private function resolvePlaceholders(array $config): array
  {
    return \str_replace(
      ['{protect}', '{watermark}'],
      [$this->protectPath(), $this->watermarkPath()],
      $config,
    );
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

  private function upload(string $name, string $mime, array $body, ?string $contents = null, int $error = \UPLOAD_ERR_OK): ServerRequestInterface
  {
    $tmp = \tempnam(\sys_get_temp_dir(), 'jdzup');
    $this->tempFiles[] = $tmp;

    \file_put_contents($tmp, $contents ?? \base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));

    return (new ServerRequestFactory())
      ->createServerRequest('POST', '/admin/json/mediamanager/upload')
      ->withParsedBody($body)
      ->withUploadedFiles([
        'fileUploadName' => new UploadedFile($tmp, $name, $mime, \filesize($tmp), $error),
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
