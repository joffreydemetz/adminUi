<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace JDZ\AdminUi\Controller;

use JDZ\AdminUi\Toolbar\AdminToolbar;
use JDZ\MediaManager\Manager\MediaManager;
use JDZ\MediaManager\ValueObject\MediaConfig;
use JDZ\MediaManager\ValueObject\OperationResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * The /nimda media manager, as one shared implementation.
 *
 * The filesystem lives in `jdz/mediamanager`, the browser lives in the admin
 * bundle's `mediamanager.js`, and this is the adapter between them : it reads
 * the request, drives the manager, translates the keys that come back and
 * shapes exactly the JSON the bundled JS already expects.
 *
 * A consuming controller is a handful of lines — the per-site part is the
 * media configuration and the thumbnailer, nothing else :
 *
 * ```php
 * final class MediasController extends AdminController
 * {
 *   use JDZ\AdminUi\Controller\MediasControllerTrait;
 *
 *   protected function mediaConfig(): MediaConfig { … }
 * }
 * ```
 *
 * ## The JS contract, which must not drift
 *
 * `mediamanager.js` calls `JiZy.makeUrl('mediamanager/<task>')` — a GET under
 * `/nimda/json/` — and reloads its whole screen from one envelope :
 * `{ mm, filesystem, breadcrumbs, stats, folders, dz }`. Modal dialogs arrive
 * as server-rendered HTML in `content`. The `imageSelector` field plugin in
 * `jizy.admin.js` calls `medias/selector` for the picker. Everything below
 * exists to honour that shape.
 *
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
trait MediasControllerTrait
{
  private ?MediaManager $mediaManagerInstance = null;
  private ?array $mediaStateCache = null;

  // ------------------------------------------------------------- per site --

  /** The library this admin drives. */
  abstract protected function mediaConfig(): MediaConfig;

  /** Translate a MEDIAMANAGER_* key, `%param%` placeholders included. */
  abstract protected function mediaTranslate(string $key, array $params = []): string;

  /** Render one modal/partial view to a string of HTML. */
  abstract protected function mediaRenderFragment(string $view, array $vData): string;

  /** Render the full media manager page through the admin chrome. */
  abstract protected function mediaRenderPage(ResponseInterface $response, string $view, array $vData): ResponseInterface;

  /** The token the modal forms and the dropzone must post back. */
  abstract protected function mediaCsrfToken(): string;

  /** @return array{folder?: string, display?: string, only?: string, file?: string} */
  abstract protected function mediaStateLoad(): array;

  abstract protected function mediaStateSave(array $state): void;

  /**
   * Thumbnail URL for a library-relative image, '' when there is none.
   * Same signature as the manager's `$thumbResolver`.
   */
  abstract protected function mediaThumbUrl(string $relPath, int $width): string;

  /**
   * Thumbnail + orientation for the picker, or null to drop the file.
   *
   * @return array{thumb: string, orientation: string}|null
   */
  abstract protected function mediaSelectorImage(string $relPath, int $width): ?array;

  // ----------------------------------------------------------- overridable --

  /** Base path of the JSON tasks — the prefix `JiZy.makeUrl()` builds. */
  protected function mediaTaskUrl(string $task): string
  {
    return '/nimda/json/mediamanager/' . \trim($task, '/');
  }

  protected function mediaDownloadUrl(string $fileName): string
  {
    return '/nimda/medias/download?fileName=' . \rawurlencode($fileName);
  }

  /**
   * How the browser addresses the library. The admin pages carry
   * `<base href="/nimda/">`, so a media URL is one level up.
   */
  protected function mediaPublicUrlBase(): string
  {
    return '../media/';
  }

  protected function mediaView(string $name): string
  {
    return 'views/admin/medias/' . $name . '.tmpl';
  }

  protected function mediaThumbWidth(): int
  {
    return 150;
  }

  protected function mediaSelectorThumbWidth(): int
  {
    return 200;
  }

  protected function mediaTitle(): string
  {
    return $this->mediaTranslate('MEDIAS');
  }

  protected function mediaFormatDate(?int $timestamp): string
  {
    return null === $timestamp ? '' : \date('d/m/Y H:i', $timestamp);
  }

  /**
   * Watermarking is only offered where the site actually ships a watermark.
   */
  protected function mediaProtectEnabled(): bool
  {
    $config = $this->mediaConfig();

    return '' !== $config->watermarkPath
      && '' !== $config->protectPath
      && true === @\is_file($config->watermarkPath);
  }

  // ------------------------------------------------------------- the page --

  /** GET /nimda/medias — the two-pane browser. */
  public function display(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    $config = $this->mediaConfig();

    $vData = [
      'component' => 'medias',
      'toolbar' => $this->mediaToolbar()->toData(),
      'showFilterbar' => false,
      'filterbar' => null,
      'stats' => $this->mediaStats(),
      'authExts' => \implode(', ', $config->authExts()),
      'authExtsImage' => \implode(', ', $config->extsImage),
      'authExtsDocument' => \implode(', ', $config->extsDocument),
      'maxWeightImage' => $config->maxWeightImage,
      'maxWeightDocument' => $config->maxWeightDocument,
    ];

    return $this->mediaRenderPage($response, $this->mediaView('display'), $vData);
  }

  /**
   * The toolbar the browser drives : every button is a `data-mm-task` the
   * bundled JS already listens for.
   */
  protected function mediaToolbar(): AdminToolbar
  {
    $toolbar = new AdminToolbar('medias');
    $toolbar->setTitle($this->mediaTitle());

    $toolbar->getToolbarButton('refresh', null, 'refresh')
      ->addStyle('btn-lg')
      ->setTip($this->mediaTranslate('REFRESH'))
      ->addDataAttr('mm-task', 'dataRefresh');

    $toolbar->getToolbarButton('afterRefresh', 'divider');

    $toolbar->getToolbarButton('new-folder', null, 'folder-plus')
      ->addStyle('btn-lg')
      ->setTip($this->mediaTranslate('TOOLBAR_MEDIA_NEW_FOLDER'))
      ->addDataAttr('mm-task', 'dirCreate');

    $toolbar->getToolbarButton('rename-folder', null, 'edit')
      ->setTip($this->mediaTranslate('TOOLBAR_MEDIA_RENAME_FOLDER'))
      ->addDataAttr('mm-task', 'dirRename');

    $toolbar->getToolbarButton('move-folder', null, 'move')
      ->setTip($this->mediaTranslate('TOOLBAR_MEDIA_MOVE_FOLDER'))
      ->addDataAttr('mm-task', 'dirMove');

    $toolbar->getToolbarButton('afterFolder', 'divider');

    $toolbar->getToolbarButton('new-file', null, 'upload')
      ->addStyle('btn-lg')
      ->setTip($this->mediaTranslate('TOOLBAR_MEDIA_NEW_FILE'))
      ->addDataAttr('mm-task', 'filUpload');

    $toolbar->getToolbarButton('afterFile', 'divider');

    $toolbar->getToolbarButton('display-thumbs', null, 'show-thumbnails')
      ->setTip($this->mediaTranslate('TOOLBAR_MEDIA_DISPLAY_THUMBS'))
      ->addDataAttr('mm-task', 'displayThumbs');

    $toolbar->getToolbarButton('display-simple', null, 'show-lines')
      ->setTip($this->mediaTranslate('TOOLBAR_MEDIA_DISPLAY_SIMPLE'))
      ->addDataAttr('mm-task', 'displaySimple');

    return $toolbar;
  }

  // ---------------------------------------------------------- browse tasks --

  /** GET json/mediamanager/init */
  public function initFs(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    return $this->mediaEnvelope($response);
  }

  /** GET json/mediamanager/fs?folder=&display=&only= */
  public function loadFs(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    $query = $request->getQueryParams();

    // An empty folder means the root, so presence decides, not truthiness.
    if (null !== ($folder = $query['folder'] ?? null)) {
      $this->mediaSet('folder', $this->mediaManager()->decodePath((string)$folder));
    }

    foreach (['display', 'only'] as $key) {
      if (null !== ($value = $query[$key] ?? null)) {
        $this->mediaSet($key, (string)$value);
      }
    }

    return $this->mediaEnvelope($response);
  }

  // ---------------------------------------------------------- folder tasks --

  /** GET json/mediamanager/folder/create */
  public function folderCreateForm(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    return $this->mediaModal(
      $response,
      'mkdir',
      ['action' => $this->mediaTaskUrl('folder/create')],
      $this->mediaTranslate('MEDIAMANAGER_DIALOG_CREATE_FOLDER'),
    );
  }

  /** POST json/mediamanager/folder/create */
  public function folderCreate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    $data = $this->mediaPosted($request);

    $result = $this->mediaManager()->createFolder($this->mediaGet('folder'), (string)($data['folderName'] ?? ''));

    if (false === $result->isSuccess()) {
      return $this->mediaFail($response, $result);
    }

    return $this->mediaSucceed($response, $result);
  }

  /** GET json/mediamanager/folder/rename */
  public function folderRenameForm(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    $folder = $this->mediaGet('folder');

    if (null !== ($refusal = $this->mediaGuardEditableFolder($response, $folder))) {
      return $refusal;
    }

    $parts = \explode('/', $folder);

    return $this->mediaModal(
      $response,
      'renamedir',
      [
        'action' => $this->mediaTaskUrl('folder/rename'),
        'oldFolder' => $folder,
        'oldName' => (string)\array_pop($parts),
      ],
      $this->mediaTranslate('MEDIAMANAGER_DIALOG_RENAME_FOLDER'),
    );
  }

  /** POST json/mediamanager/folder/rename */
  public function folderRename(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    $data = $this->mediaPosted($request);

    $result = $this->mediaManager()->renameFolder($this->mediaGet('folder'), (string)($data['newName'] ?? ''));

    if (false === $result->isSuccess()) {
      return $this->mediaFail($response, $result);
    }

    $this->mediaSet('folder', (string)$result->get('folder', ''));

    return $this->mediaSucceed($response, $result);
  }

  /** GET json/mediamanager/folder/move */
  public function folderMoveForm(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    $folder = $this->mediaGet('folder');

    if (null !== ($refusal = $this->mediaGuardEditableFolder($response, $folder))) {
      return $refusal;
    }

    $parts = \explode('/', $folder);

    return $this->mediaModal(
      $response,
      'movedir',
      [
        'action' => $this->mediaTaskUrl('folder/move'),
        'oldFolder' => $folder,
        'oldName' => (string)\array_pop($parts),
        'folderTree' => $this->mediaManager()->getFolderSelectList(),
      ],
      $this->mediaTranslate('MEDIAMANAGER_DIALOG_MOVE_FOLDER'),
    );
  }

  /** POST json/mediamanager/folder/move */
  public function folderMove(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    $data = $this->mediaPosted($request);

    $result = $this->mediaManager()->moveFolder($this->mediaGet('folder'), (string)($data['newFolder'] ?? ''));

    if (false === $result->isSuccess()) {
      return $this->mediaFail($response, $result);
    }

    $this->mediaSet('folder', (string)$result->get('folder', ''));

    return $this->mediaSucceed($response, $result);
  }

  /** GET json/mediamanager/folder/delete */
  public function folderDelete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    $result = $this->mediaManager()->deleteFolder($this->mediaGet('folder'));

    if (false === $result->isSuccess()) {
      return $this->mediaFail($response, $result);
    }

    $parent = (string)$result->get('parent', '');

    $this->mediaSet('folder', $parent);

    return $this->mediaSucceed($response, $result, ['parent' => $parent]);
  }

  // ------------------------------------------------------------ file tasks --

  /** GET json/mediamanager/file/infos?fileName= */
  public function fileInfos(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    $fileName = (string)($request->getQueryParams()['fileName'] ?? '');

    $this->mediaSet('file', '');

    $infos = $this->mediaManager()->getFileInfos($this->mediaGet('folder'), $fileName);

    if (null === $infos) {
      return $this->mediaJson($response, ['error' => $this->mediaTranslate('MEDIAMANAGER_ERROR_SOURCE_FILE_NOT_FOUND')]);
    }

    $download = $this->mediaDownloadUrl($fileName);

    $file = [
      'name' => $infos->name,
      'download' => $download,
      'namenoext' => $infos->namenoext,
      'ext' => $infos->ext,
      'path' => $infos->folder,
      'mime' => $infos->mime,
      'size' => \round($infos->sizeBytes / 1000, 2),
      'created' => $this->mediaFormatDate($infos->createdAt),
      'modified' => $this->mediaFormatDate($infos->modifiedAt),
      'thumb' => '',
    ];

    // The details pane prints these as raw HTML, so anything drawn from the
    // filesystem is escaped here rather than in the template.
    $file['infos'] = [
      '<strong>' . $this->mediaEscape($infos->name) . '</strong>',
      $this->mediaTranslate('TYPE') . ' : ' . $this->mediaEscape($file['mime']),
      $this->mediaTranslate('SIZE') . ' : ' . $file['size'] . 'Ko',
      $this->mediaTranslate('CREATED') . ' : ' . $file['created'],
      $this->mediaTranslate('MODIFIED') . ' : ' . $file['modified'],
    ];

    if (true === $infos->isImage) {
      $file['thumb'] = $download;
      $file['infos'][] = 'W: ' . $infos->width . 'px / H: ' . $infos->height . 'px';
    }

    $this->mediaSet('file', $fileName);

    return $this->mediaJson($response, [
      'content' => $this->mediaRenderFragment($this->mediaView('finfos'), [
        'file' => $file,
        'canProtect' => $this->mediaProtectEnabled(),
      ]),
      'finfos' => $file,
      'noheader' => true,
      'closeIcon' => true,
      'size' => 'sm',
    ]);
  }

  /** GET json/mediamanager/file/rename */
  public function fileRenameForm(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    $fileName = $this->mediaGet('file');

    if ('' === $fileName) {
      return $this->mediaJson($response, ['error' => $this->mediaTranslate('MEDIAMANAGER_ERROR_NO_FILE_SPECIFIED')]);
    }

    $fi = new \SplFileInfo($fileName);
    $ext = $fi->getExtension();

    return $this->mediaModal(
      $response,
      'rename',
      [
        'action' => $this->mediaTaskUrl('file/rename'),
        'fileName' => $fi->getBasename('' === $ext ? '' : '.' . $ext),
        'fileExt' => '' === $ext ? '' : '.' . $ext,
      ],
      $this->mediaTranslate('MEDIAMANAGER_DIALOG_RENAME_FILE'),
    );
  }

  /** POST json/mediamanager/file/rename */
  public function fileRename(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    $data = $this->mediaPosted($request);

    $result = $this->mediaManager()->renameFile(
      $this->mediaGet('folder'),
      $this->mediaGet('file'),
      (string)($data['newName'] ?? ''),
    );

    if (false === $result->isSuccess()) {
      return $this->mediaFail($response, $result);
    }

    $this->mediaSet('file', (string)$result->get('file', ''));

    return $this->mediaSucceed($response, $result);
  }

  /** GET json/mediamanager/file/move */
  public function fileMoveForm(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    $fileName = $this->mediaGet('file');

    if ('' === $fileName) {
      return $this->mediaJson($response, ['error' => $this->mediaTranslate('MEDIAMANAGER_ERROR_NO_FILE_SPECIFIED')]);
    }

    return $this->mediaModal(
      $response,
      'move',
      [
        'action' => $this->mediaTaskUrl('file/move'),
        'fileName' => $fileName,
        'oldFolder' => $this->mediaGet('folder'),
        'folderTree' => $this->mediaManager()->getFolderSelectList(),
      ],
      $this->mediaTranslate('MEDIAMANAGER_DIALOG_MOVE_FILE'),
    );
  }

  /** POST json/mediamanager/file/move */
  public function fileMove(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    $data = $this->mediaPosted($request);

    $result = $this->mediaManager()->moveFile(
      $this->mediaGet('folder'),
      (string)($data['fileName'] ?? ''),
      (string)($data['newFolder'] ?? ''),
    );

    if (false === $result->isSuccess()) {
      return $this->mediaFail($response, $result);
    }

    $this->mediaSet('folder', (string)$result->get('folder', ''));

    return $this->mediaSucceed($response, $result);
  }

  /** GET json/mediamanager/file/delete?fileName= */
  public function fileDelete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    $fileName = (string)($request->getQueryParams()['fileName'] ?? '');

    $result = $this->mediaManager()->deleteFile($this->mediaGet('folder'), $fileName);

    if (false === $result->isSuccess()) {
      return $this->mediaFail($response, $result);
    }

    $this->mediaSet('file', '');

    return $this->mediaSucceed($response, $result);
  }

  /** GET json/mediamanager/file/protect?fileName= */
  public function fileProtect(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    if (null !== ($refusal = $this->mediaGuardProtect($response))) {
      return $refusal;
    }

    $fileName = (string)($request->getQueryParams()['fileName'] ?? '');

    $result = $this->mediaManager()->protectImage($this->mediaGet('folder'), $fileName);

    if (false === $result->isSuccess()) {
      return $this->mediaFail($response, $result);
    }

    return $this->mediaJson($response, [
      'success' => true,
      'updateWatermark' => true === $result->get('updated', false),
      'message' => $this->mediaTranslate($result->key, $result->params),
    ]);
  }

  /** GET json/mediamanager/file/unprotect?fileName= */
  public function fileUnprotect(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    if (null !== ($refusal = $this->mediaGuardProtect($response))) {
      return $refusal;
    }

    $fileName = (string)($request->getQueryParams()['fileName'] ?? '');

    $result = $this->mediaManager()->unprotectImage($this->mediaGet('folder'), $fileName);

    if (false === $result->isSuccess()) {
      return $this->mediaFail($response, $result);
    }

    return $this->mediaJson($response, [
      'success' => true,
      'message' => $this->mediaTranslate($result->key, $result->params),
    ]);
  }

  /** GET /nimda/medias/download?fileName= */
  public function fileDownload(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    $folder = $this->mediaGet('folder');
    $fileName = (string)($request->getQueryParams()['fileName'] ?? '');

    $fullpath = $this->mediaManager()->resolveDownloadPath($folder, $fileName);

    if (null === $fullpath) {
      return $response->withStatus(404);
    }

    $handle = @\fopen($fullpath, 'rb');

    if (false === $handle) {
      return $response->withStatus(404);
    }

    $body = $response->getBody();

    while (false === \feof($handle)) {
      $chunk = \fread($handle, 8192);

      if (false === $chunk) {
        break;
      }

      $body->write($chunk);
    }

    \fclose($handle);

    $mime = $this->mediaManager()->getFileInfos($folder, $fileName)?->mime;

    return $response
      ->withHeader('Content-Type', '' !== (string)$mime ? (string)$mime : 'application/octet-stream')
      // A media file is never HTML, but the browser is the one that decides —
      // pin the type it may sniff to and hand it a name.
      ->withHeader('X-Content-Type-Options', 'nosniff')
      ->withHeader('Content-Disposition', 'inline; filename="' . \str_replace(['"', "\r", "\n"], '', $fileName) . '"');
  }

  // ---------------------------------------------------------------- upload --

  /**
   * POST json/mediamanager/upload — the dropzone endpoint, shared by the
   * browser and by the picker's inline uploader.
   */
  public function upload(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    $posted = (array)$request->getParsedBody();

    $force = 1 === (int)($posted['force'] ?? 0);
    $folder = (string)($posted['folder'] ?? '');

    $data = ['force' => $force, 'folder' => $folder, 'status' => 403];

    $file = $request->getUploadedFiles()['fileUploadName'] ?? null;

    if (false === $file instanceof UploadedFileInterface || \UPLOAD_ERR_OK !== $file->getError()) {
      $data['error'] = $this->mediaTranslate('MEDIAMANAGER_ERROR_NO_FILE_SPECIFIED');

      return $this->mediaJson($response->withStatus(403), $data);
    }

    // Belt and braces on top of the uploader's mime check: the extension the
    // file will be STORED under is taken from the client-supplied name, so it
    // is the extension that has to clear the whitelist.
    $ext = \strtolower((new \SplFileInfo((string)($file->getClientFilename() ?? '')))->getExtension());
    $authExts = \array_map('strtolower', $this->mediaConfig()->authExts());

    if ('' === $ext || false === \in_array($ext, $authExts, true)) {
      $data['error'] = $this->mediaTranslate('MEDIAMANAGER_ERROR_UPLOAD_FILE_UNAUTH_EXT', [
        '%authExts%' => \implode(', ', $this->mediaConfig()->authExts()),
      ]);

      return $this->mediaJson($response->withStatus(403), $data);
    }

    try {
      $result = $this->mediaManager()->upload(PsrUploadedFileBridge::toHttpFoundation($file), $folder, $force);
    } catch (\Throwable $e) {
      $data['error'] = $this->mediaTranslate('MEDIAMANAGER_ERROR_OPERATION_FAILED', ['%error%' => $e->getMessage()]);

      return $this->mediaJson($response->withStatus(403), $data);
    }

    if (false === $result->isSuccess()) {
      $data['error'] = $this->mediaTranslate($result->key, $result->params);

      return $this->mediaJson($response->withStatus(403), $data);
    }

    $data = \array_merge($data, $result->toArray());
    $data['url'] = $this->mediaPublicUrlBase() . $result->value();
    $data['status'] = 200;

    return $this->mediaJson($response, $data);
  }

  // -------------------------------------------------------------- selector --

  /**
   * GET json/medias/selector?folder= — the picker `imageSelector` opens on a
   * form field.
   */
  public function selector(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    $config = $this->mediaConfig();

    $folder = $this->mediaManager()->decodePath((string)($request->getQueryParams()['folder'] ?? ''));

    $tree = $this->mediaManager()->getSelectorList(
      $folder,
      $this->mediaPublicUrlBase(),
      fn(string $relPath): ?array => $this->mediaSelectorImage($relPath, $this->mediaSelectorThumbWidth()),
    );

    return $this->mediaJson($response, [
      'content' => $this->mediaRenderFragment($this->mediaView('selector'), [
        'folder' => $folder,
        'tree' => $tree,
        'authExtsImage' => \implode(', ', $config->extsImage),
        'maxWeightImage' => $config->maxWeightImage,
      ]),
      'dz' => $this->mediaDropzoneConfig('image'),
      'stats' => $this->mediaStats(),
    ]);
  }

  // ------------------------------------------------------------- internals --

  protected function mediaManager(): MediaManager
  {
    return $this->mediaManagerInstance ??= new MediaManager($this->mediaConfig());
  }

  /**
   * The refresh envelope the admin JS reloads the whole screen from.
   */
  protected function mediaEnvelope(ResponseInterface $response, array $data = []): ResponseInterface
  {
    $folder = $this->mediaGet('folder');

    return $this->mediaJson($response, \array_merge($data, [
      'dz' => $this->mediaDropzoneConfig(),
      'stats' => $this->mediaStats(),
      'breadcrumbs' => $this->mediaManager()->getBreadcrumbs($folder),
      'filesystem' => $this->mediaManager()->getFilesystem(
        $folder,
        $this->mediaGet('only'),
        fn(string $relPath, string $fileName): string => $this->mediaThumbUrl($relPath, $this->mediaThumbWidth()),
      ),
      'folders' => $this->mediaManager()->getFolderSelectList(),
      'mm' => [
        'folder' => $this->mediaGet('folder'),
        'display' => $this->mediaGet('display'),
        'only' => $this->mediaGet('only'),
        'file' => $this->mediaGet('file'),
      ],
    ]));
  }

  protected function mediaDropzoneConfig(string $uploadType = ''): array
  {
    return \array_merge(
      [
        'url' => $this->mediaTaskUrl('upload'),
        // The BO guards every POST on a token; the dropzone builds its own
        // body, so the token has to ride along as a Dropzone param.
        'params' => ['_csrf' => $this->mediaCsrfToken()],
      ],
      $this->mediaManager()->getDropzoneParams($uploadType),
    );
  }

  /**
   * Filesystem counters, with the quota complaints already translated.
   */
  protected function mediaStats(): array
  {
    $stats = $this->mediaManager()->getStats();

    $data = $stats->toArray();
    $data['errors'] = [];

    foreach ($stats->errors as $error) {
      $data['errors'][] = $this->mediaTranslate($error['key'], $error['params']);
    }

    return $data;
  }

  /**
   * A modal dialog : server-rendered HTML plus the chrome `JiZy.Admin.modal`
   * reads.
   */
  protected function mediaModal(ResponseInterface $response, string $view, array $vData, string $title, string $size = 'sm'): ResponseInterface
  {
    $vData['csrf'] = $this->mediaCsrfToken();

    return $this->mediaJson($response, [
      'content' => $this->mediaRenderFragment($this->mediaView($view), $vData),
      'title' => $title,
      'noheader' => false,
      'closeIcon' => true,
      'size' => $size,
    ]);
  }

  /** The `m[...]` bag every media modal form posts. */
  protected function mediaPosted(ServerRequestInterface $request): array
  {
    $body = (array)$request->getParsedBody();

    return (array)($body['m'] ?? []);
  }

  /** Emit a failed operation — `JiZy.onAjaxResponse` surfaces `error`. */
  protected function mediaFail(ResponseInterface $response, OperationResult $result): ResponseInterface
  {
    return $this->mediaJson($response, [
      'error' => $this->mediaTranslate($result->key, $result->params),
    ]);
  }

  /** Emit a successful operation, followed by a full screen refresh. */
  protected function mediaSucceed(ResponseInterface $response, OperationResult $result, array $data = []): ResponseInterface
  {
    return $this->mediaEnvelope($response, \array_merge($data, [
      'success' => true,
      'message' => $this->mediaTranslate($result->key, $result->params),
    ]));
  }

  /**
   * Refuse anything structural before even opening a dialog.
   */
  protected function mediaGuardEditableFolder(ResponseInterface $response, string $folder): ?ResponseInterface
  {
    if ('' === $folder) {
      return $this->mediaJson($response, ['error' => $this->mediaTranslate('MEDIAMANAGER_ERROR_ROOT_CANNOT_BE_CHANGED')]);
    }

    if (true === $this->mediaManager()->isSystemFolder($folder)) {
      return $this->mediaJson($response, ['error' => $this->mediaTranslate('MEDIAMANAGER_ERROR_ROOT_FOLDERS_CANNOT_BE_CHANGED')]);
    }

    return null;
  }

  /**
   * The buttons are hidden when watermarking is off ; the endpoints stay
   * routed, so they refuse on their own.
   */
  protected function mediaGuardProtect(ResponseInterface $response): ?ResponseInterface
  {
    if (true === $this->mediaProtectEnabled()) {
      return null;
    }

    return $this->mediaJson($response, ['error' => $this->mediaTranslate('MEDIAMANAGER_ERROR_PROTECT_DISABLED')]);
  }

  protected function mediaJson(ResponseInterface $response, array $payload): ResponseInterface
  {
    $response->getBody()->write((string)\json_encode($payload, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE));

    return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
  }

  protected function mediaEscape(string $value): string
  {
    return \htmlspecialchars($value, \ENT_QUOTES, 'UTF-8');
  }

  // ----------------------------------------------------------------- state --

  /**
   * Which folder / display mode / filter / file the session is looking at.
   * The browser mirrors it in `JiZy.local`, but the server is what decides.
   */
  protected function mediaGet(string $key): string
  {
    if (null === $this->mediaStateCache) {
      $loaded = $this->mediaStateLoad();

      $this->mediaStateCache = [
        'folder' => (string)($loaded['folder'] ?? ''),
        'display' => (string)($loaded['display'] ?? ''),
        'only' => (string)($loaded['only'] ?? ''),
        'file' => (string)($loaded['file'] ?? ''),
      ];
    }

    return $this->mediaStateCache[$key] ?? '';
  }

  protected function mediaSet(string $key, string $value): void
  {
    // Seeds the cache before writing into it.
    $this->mediaGet($key);

    $this->mediaStateCache[$key] = $value;

    $this->mediaStateSave($this->mediaStateCache);
  }
}
