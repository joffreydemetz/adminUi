<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace JDZ\AdminUi\Controller\Traits;

use JDZ\AdminUi\Controller\PsrUploadedFileBridge;
use JDZ\AdminUi\Toolbar\AdminToolbar;
use JDZ\MediaManager\Manager\MediaManager;
use JDZ\MediaManager\ValueObject\MediaConfig;
use JDZ\MediaManager\ValueObject\OperationResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * The /admin media manager, as one shared implementation.
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
 *   use JDZ\AdminUi\Controller\Traits\MediasControllerTrait;
 *
 *   protected function mediaConfig(): MediaConfig { … }
 * }
 * ```
 *
 * ## The JS contract, which must not drift
 *
 * `mediamanager.js` calls `JiZy.makeUrl('mediamanager/<task>')` — a GET under
 * `/admin/json/` — and reloads its whole screen from one envelope :
 * `{ mm, filesystem, breadcrumbs, stats, folders, dz }`. Modal dialogs arrive
 * as server-rendered HTML in `content`. The `imageSelector` field plugin in
 * `jizy.admin.js` calls `medias/selector` for the picker. Everything below
 * exists to honour that shape.
 *
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
trait MediasControllerTrait
{
  /** The picker mode the rich-text editor opens the library in. */
  public const MEDIA_MODE_EDITOR = 'editor';

  /** How a figure may be anchored — the front CSS's `pull-*` classes. */
  public const MEDIA_ALIGNMENTS = ['left', 'right'];

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

  /**
   * The prefix every media URL hangs off. A site's medias controller is
   * standalone — it does not extend the one that carries
   * {@see AdminControllerTrait::adminBaseUrl()} — so it gets its own hook, and
   * a site that serves its back office elsewhere overrides both.
   */
  protected function mediaBaseUrl(): string
  {
    return '/admin';
  }

  /** Base path of the JSON tasks — the prefix `JiZy.makeUrl()` builds. */
  protected function mediaTaskUrl(string $task): string
  {
    return $this->mediaBaseUrl() . '/json/mediamanager/' . \trim($task, '/');
  }

  protected function mediaDownloadUrl(string $fileName): string
  {
    return $this->mediaBaseUrl() . '/medias/download?fileName=' . \rawurlencode($fileName);
  }

  /**
   * How the browser addresses the library. The admin pages carry
   * `<base href="{@see mediaBaseUrl()}/">`, so a media URL is one level up.
   */
  protected function mediaPublicUrlBase(): string
  {
    return '../media/';
  }

  /**
   * Where the library screens live, as this site names them.
   *
   * The media controller is standalone — it does not use
   * {@see AdminControllerTrait} — so it carries its own copy of that trait's
   * `adminViewName()` seam. A site whose templates are
   * `templates/admin/views/medias/<name>.twig` overrides this one too. The
   * default is the historical name.
   */
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

  /** GET /admin/medias — the two-pane browser. */
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

  /**
   * POST json/mediamanager/folder/create
   *
   * The media manager screen creates inside whatever folder the session says it
   * is looking at; the picker has its own folder select and is not that screen,
   * so it posts `parent` explicitly. The created folder comes back in `folder`
   * so the caller can reload straight into it.
   */
  public function folderCreate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    $data = $this->mediaPosted($request);

    $parent = \array_key_exists('parent', $data)
      ? $this->mediaManager()->decodePath((string)$data['parent'])
      : $this->mediaGet('folder');

    $name = (string)($data['folderName'] ?? '');

    $result = $this->mediaManager()->createFolder($parent, $name);

    if (false === $result->isSuccess()) {
      return $this->mediaFail($response, $result);
    }

    return $this->mediaSucceed($response, $result, [
      'folder' => \trim(('' === $parent ? '' : $parent . '/') . \trim($name), '/'),
    ]);
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

  /** GET /admin/medias/download?fileName= */
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

    $mime = (string)$this->mediaManager()->getFileInfos($folder, $fileName)?->mime;
    $mime = '' !== $mime ? $mime : 'application/octet-stream';

    // Two belts against a media file running script in the back-office origin
    // (D34): a type that can carry script (SVG, HTML, XML, any text) is handed
    // over as a download, and every response is sandboxed, so even an inline
    // one gets an opaque origin with no script. nosniff pins the type.
    $disposition = $this->mediaServedAsAttachment($mime) ? 'attachment' : 'inline';

    return $response
      ->withHeader('Content-Type', $mime)
      ->withHeader('X-Content-Type-Options', 'nosniff')
      ->withHeader('Content-Security-Policy', 'sandbox')
      ->withHeader('Content-Disposition', $disposition . '; filename="' . \str_replace(['"', "\r", "\n"], '', $fileName) . '"');
  }

  /**
   * Is this a type a browser would render as a document (and so run script
   * from) rather than display as a picture or hand to a plugin ?
   */
  protected function mediaServedAsAttachment(string $mime): bool
  {
    $mime = \strtolower(\trim(\explode(';', $mime, 2)[0]));

    return 'image/svg+xml' === $mime
      || \str_starts_with($mime, 'text/')
      || 'application/xml' === $mime
      || 'application/xhtml+xml' === $mime;
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
   * GET json/medias/selector?folder=&mode= — the picker `JiZy.Admin.mediaPicker`
   * opens, both on a form field and from the rich-text editor.
   *
   * `mode=editor` adds the caption / alt / anchoring fieldset the editor needs
   * to build its `<figure>`, prefilled from the figure being edited when there
   * is one. The folder list rides along in both modes so the picker can switch
   * folders without going through the media manager screen.
   */
  public function selector(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    $config = $this->mediaConfig();
    $query = $request->getQueryParams();

    $folder = $this->mediaManager()->decodePath((string)($query['folder'] ?? ''));
    $mode = self::MEDIA_MODE_EDITOR === (string)($query['mode'] ?? '') ? self::MEDIA_MODE_EDITOR : '';

    $tree = $this->mediaManager()->getSelectorList(
      $folder,
      $this->mediaPublicUrlBase(),
      fn(string $relPath): ?array => $this->mediaSelectorImage($relPath, $this->mediaSelectorThumbWidth()),
    );

    return $this->mediaJson($response, [
      'content' => $this->mediaRenderFragment($this->mediaView('selector'), [
        'folder' => $folder,
        'folders' => $this->mediaManager()->getFolderSelectList(),
        'mode' => $mode,
        'current' => self::MEDIA_MODE_EDITOR === $mode ? $this->mediaCurrentFigure($query) : null,
        'tree' => $tree,
        'authExtsImage' => \implode(', ', $config->extsImage),
        'maxWeightImage' => $config->maxWeightImage,
      ]),
      'dz' => $this->mediaDropzoneConfig('image'),
      'stats' => $this->mediaStats(),
    ]);
  }

  /**
   * The figure the editor is re-opening the picker on, or null when it is
   * inserting a new one. This is query-string input on its way back out through
   * a view, so `align` is whitelisted rather than echoed.
   *
   * @param array<string,mixed> $query
   * @return array{src: string, alt: string, caption: string, align: string}|null
   */
  protected function mediaCurrentFigure(array $query): ?array
  {
    $src = \trim((string)($query['src'] ?? ''));
    if ('' === $src) {
      return null;
    }

    $align = (string)($query['align'] ?? '');

    return [
      'src' => $src,
      'alt' => (string)($query['alt'] ?? ''),
      'caption' => (string)($query['caption'] ?? ''),
      'align' => \in_array($align, self::MEDIA_ALIGNMENTS, true) ? $align : '',
    ];
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
