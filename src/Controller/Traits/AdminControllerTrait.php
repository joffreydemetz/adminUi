<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace JDZ\AdminUi\Controller\Traits;

use JDZ\AdminUi\Contract\AdminServiceInterface;
use JDZ\AdminUi\Contract\ListState;
use JDZ\AdminUi\Form\FiltersForm;
use JDZ\AdminUi\Form\FormView;
use JDZ\AdminUi\List\Columns;
use JDZ\AdminUi\List\Row;
use JDZ\AdminUi\Toolbar\AdminToolbar;
use JDZ\AdminUi\ValueObject\Filterbar;
use JDZ\Form\Form;
use JDZ\Form\FormData;
use JDZ\Renderer\Pager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * One entity admin, as one shared implementation.
 *
 * The /admin plumbing on the callisto admin contract : framework task URLs
 * ({component}/list|add|edit|save|apply|publish|unpublish|trash|cancel +
 * json publish/unpublish/changeorder/setFilters/resetFilters), adminUi value
 * objects for the toolbar/columns/rows/filterbar, layouts/** views rendered by
 * the site's admin renderer.
 *
 * A consuming controller is its component slug, its title, its service, and how
 * a row and a form look — nothing else :
 *
 * ```php
 * final class FormationsController extends AdminController
 * {
 *   protected function component(): string { return 'formations'; }
 *   protected function title(): string { return 'Formations'; }
 *   protected function service(): AdminServiceInterface { return $this->service; }
 *   protected function buildRow(object $item, bool $orderValid): Row { … }
 *   protected function buildForm(?object $item, array $input = []): Form { … }
 * }
 * ```
 *
 * The site binds the trait to its own chrome once, in an abstract
 * `AdminController` carrying the six `admin*` hooks below — the same shape
 * MediasControllerTrait uses for its `mediaRender*` / `mediaState*` pair.
 *
 * ## The JS contract, which must not drift
 *
 * `jizy.admin.js` rewrites the toolbar hrefs with `?id=1,2,3`, posts the
 * filterbar to `json/{component}/setFilters/`, expects `{success:true}` off
 * changeorder, and reads the list vData keys `ordervalid / orderDir /
 * listHeaders / items / pagination / filterbar.filterActive`. A failed save is
 * a 422 re-render of the posted values, never a redirect.
 *
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
trait AdminControllerTrait
{
  // -------------------------------------------------------- per component --

  /** The component slug (formations|photos) — drives routes + body classes. */
  abstract protected function component(): string;

  /** Human list title (toolbar h2). */
  abstract protected function title(): string;

  /** Everything this admin knows how to read and write. */
  abstract protected function service(): AdminServiceInterface;

  /** Turn an entity into an adminUi Row (label/triggers filled). */
  abstract protected function buildRow(object $item, bool $orderValid): Row;

  /** The entity admin form, values filled for $item (null = blank). */
  abstract protected function buildForm(?object $item, array $input = []): Form;

  // ------------------------------------------------------------- per site --

  /** Render one admin view through the site chrome. */
  abstract protected function adminRender(ResponseInterface $response, string $view, array $vData, string $component, string $task): ResponseInterface;

  abstract protected function adminFlash(string $message, string $type = 'success'): void;

  /** The token the admin forms must post back. */
  abstract protected function adminCsrfToken(): string;

  /** @return array{search:string, orderBy:string, limit:int} the session filter state */
  abstract protected function adminFilterState(string $component): array;

  abstract protected function adminFiltersSet(string $component, array $filter): void;

  abstract protected function adminFiltersReset(string $component): void;

  // ---------------------------------------------------------------- tasks --

  public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    $component = $this->component();
    $state = $this->adminFilterState($component);
    $limit = $state['limit'];

    $total = $this->service()->count(ListState::fromFilters($state));
    $pages = $limit > 0 ? \max(1, (int) \ceil($total / $limit)) : 1;
    $page = \max(1, \min($pages, (int) ($request->getQueryParams()['page'] ?? 1)));
    $offset = $limit > 0 ? ($page - 1) * $limit : 0;

    $orderValid = $this->orderingValid($state);

    $columns = $this->buildColumns($state);
    $items = [];
    foreach ($this->service()->list(ListState::fromFilters($state, $offset)) as $item) {
      $items[] = (object) $this->buildRow($item, $orderValid)->toRowData($columns);
    }

    $vData = [
      'component' => $component,
      'toolbar' => $this->buildToolbar($total)->toData(),
      'showFilterbar' => $this->showFilterbar(),
      'filterbar' => $this->buildFilterbar($state, $orderValid),
      'exporters' => null,
      'exportFilters' => null,
      'importers' => null,
      'back2parent' => null,
      'groupBy' => null,
      'ordervalid' => $orderValid,
      'orderDir' => \str_ends_with($state['orderBy'], 'DESC') ? 'DESC' : 'ASC',
      'total' => $total,
      'listHeaders' => $columns->toData(),
      'items' => $items,
      'pagination' => $this->buildPagination($pages, $page),
    ];

    return $this->adminRender($response, $this->adminViewName('items'), $vData, $component, 'list');
  }

  public function add(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    return $this->renderItem($response, $this->buildForm(null), null);
  }

  public function edit(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    $id = (int) ($request->getQueryParams()['id'] ?? 0);
    $item = $id > 0 ? $this->service()->find($id) : null;
    if (!$item) {
      $this->adminFlash($this->adminText('notFound'), 'error');

      return $this->redirect($response, $this->listUrl());
    }

    return $this->renderItem($response, $this->buildForm($item), $id);
  }

  public function save(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    return $this->handleSave($request, $response, false);
  }

  public function apply(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    return $this->handleSave($request, $response, true);
  }

  public function cancel(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    return $this->redirect($response, $this->listUrl());
  }

  public function publish(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    return $this->togglePublished($request, $response, 1, $this->adminText('selectionPublished'));
  }

  public function unpublish(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    return $this->togglePublished($request, $response, 0, $this->adminText('selectionUnpublished'));
  }

  public function trash(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    $ids = $this->idsFromQuery($request);
    if ($ids) {
      $this->service()->delete($ids);
      $this->adminFlash($this->adminText(\count($ids) > 1 ? 'deletedMany' : 'deleted'));
    }

    return $this->redirect($response, $this->listUrl());
  }

  /** POST /admin/json/{c}/publish/ — the Publié picto on a list row. */
  public function jsonPublish(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    return $this->jsonSetPublished($request, $response, 1);
  }

  /** POST /admin/json/{c}/unpublish/ — same picto, the other way. */
  public function jsonUnpublish(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    return $this->jsonSetPublished($request, $response, 0);
  }

  /** POST /admin/json/{c}/changeorder/ (GET kept for pre-POST bundles) — {success:true} for admin.js. */
  public function changeorder(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    $q = \array_merge($request->getQueryParams(), (array) $request->getParsedBody());
    $id = (int) ($q['id'] ?? 0);
    $newValue = (int) ($q['newValue'] ?? 0);
    if ($id > 0 && $newValue > 0) {
      $this->service()->moveOrdering($id, $newValue);
    }

    return $this->json($response, ['success' => true]);
  }

  /** POST /admin/json/{c}/setFilters/ — store filter[...] in session. */
  public function setFilters(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    $data = (array) $request->getParsedBody();
    $this->adminFiltersSet($this->component(), (array) ($data['filter'] ?? []));

    return $this->json($response, ['success' => true]);
  }

  public function resetFilters(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    $this->adminFiltersReset($this->component());

    return $this->json($response, ['success' => true]);
  }

  // -------------------------------------------------------------- helpers --

  /** The /admin prefix every task URL hangs off. */
  protected function adminBaseUrl(): string
  {
    return '/admin';
  }

  /**
   * Where the shared admin screens live, as this site names them.
   *
   * The package renders `items` and `item`; it does not get to decide the
   * folder or the extension they sit under. A site whose templates are
   * `templates/admin/views/<name>.twig` overrides this once, in its own
   * abstract admin controller. The default is the historical name, so a
   * consumer that overrides nothing keeps rendering what it always did.
   *
   * @see MediasControllerTrait::mediaView() the same seam for the library
   */
  protected function adminViewName(string $name): string
  {
    return 'views/admin/' . $name . '.tmpl';
  }

  protected function buildColumns(array $state): Columns
  {
    $columns = new Columns($this->component());
    $columns->createColumn('checkbox');
    $columns->createColumn('label', 'label', $this->adminText('colLabel'));
    $columns->createColumn('published', 'boolean', $this->adminText('colPublished'));
    $columns->createColumn('ordering', 'ordering', $this->adminText('colOrdering'));
    $columns->createColumn('id', 'id', $this->adminText('colId'));
    $columns->createColumn('triggers');

    return $columns;
  }

  protected function buildToolbar(int $total): AdminToolbar
  {
    $component = $this->component();
    $base = $this->adminBaseUrl() . '/' . $component;
    $toolbar = new AdminToolbar($component);
    $toolbar->setTitle($this->title());
    $toolbar->setSubtitle('(' . $total . ')');

    $toolbar->getToolbarButton('add', null, 'plus')
      ->addStyle('btn-lg')
      ->setHref($base . '/add/')
      ->setTip($this->adminText('add'));

    $toolbar->getToolbarButton('beforePublish', 'divider');

    $toolbar->getToolbarButton('publish', 'selection', 'ok')
      ->setHref($base . '/publish/')
      ->setTip($this->adminText('publish'))
      ->addDataAttr('selection', $this->adminText('selectionRequired'))
      ->addDataAttr('confirm', $this->adminText('confirmPublish'));

    $toolbar->getToolbarButton('unpublish', 'selection', 'ban-circle')
      ->setHref($base . '/unpublish/')
      ->setTip($this->adminText('unpublish'))
      ->addDataAttr('selection', $this->adminText('selectionRequired'))
      ->addDataAttr('confirm', $this->adminText('confirmUnpublish'));

    $toolbar->getToolbarButton('beforeTrash', 'divider');

    $toolbar->getToolbarButton('trash', 'selection', 'bin')
      ->setHref($base . '/trash/')
      ->setTip($this->adminText('trash'))
      ->addDataAttr('selection', $this->adminText('selectionRequired'))
      ->addDataAttr('confirm', $this->adminText('confirmTrash'));

    return $toolbar;
  }

  /** The filterbar arrays header.tmpl reads, from the FiltersForm toData. */
  protected function buildFilterbar(array $state, bool $orderValid): array
  {
    $filterOptions = $this->filterOptions();

    $form = new FiltersForm('filters');
    $form->withBaseUrl($this->adminBaseUrl())
      ->configure($this->component(), $this->listUrl(), $this->adminCsrfToken(), $state, $orderValid, $filterOptions)
      ->withSearchbox($this->hasSearch());
    // Values ride the FormData (bare field names) — the form lifecycle
    // overwrites any setValue() done in populate() with these.
    $values = [
      'search' => $state['search'],
      'limit' => (string) $state['limit'],
      'component' => $this->component(),
      'listUrl' => $this->listUrl(),
      '_csrf' => $this->adminCsrfToken(),
    ];
    foreach (\array_keys($filterOptions) as $name) {
      $values[$name] = (string) ($state[$name] ?? '');
    }
    $form->init(new FormData($values));

    $filterbar = Filterbar::fromFormData($form->toData())->toArray();
    // filterActive opens the bar on load (body.filterbarOpen) — a search,
    // or any extra filter off its "all" (empty / 0) value.
    $filterActive = '' !== $state['search'];
    foreach (\array_keys($filterOptions) as $name) {
      $value = (string) ($state[$name] ?? '');
      $filterActive = $filterActive || ('' !== $value && '0' !== $value);
    }
    $filterbar['filterActive'] = $filterActive;

    return $filterbar;
  }

  protected function buildPagination(int $pages, int $page): ?array
  {
    if ($pages <= 1) {
      return null;
    }

    $pager = new Pager();
    $pager->addStyles('pager round');
    // French arrow tooltips — the renderer defaults to Previous/Next.
    $pager->setPrevText($this->adminText('pagerPrev'));
    $pager->setNextText($this->adminText('pagerNext'));
    $pager->load($pages, $page, $this->listUrl());

    return $pager->toData();
  }

  /**
   * The searchbox is off by default: these lists are short, and the
   * framework's `code:` search vocabulary behind the old help text was never
   * ported. A component with enough rows to need it overrides this.
   */
  protected function hasSearch(): bool
  {
    return false;
  }

  /** No searchbox and no extra selects leaves nothing to open the bar for. */
  protected function showFilterbar(): bool
  {
    return $this->hasSearch() || [] !== $this->filterOptions();
  }

  /** The sort drag-reorder needs — patricia lists newest first, so DESC there. */
  protected function orderingSort(): string
  {
    return 'ordering ASC';
  }

  /** Drag-reorder only makes sense while the ordering sort is the active one. */
  protected function orderingValid(array $state): bool
  {
    return $this->orderingSort() === $state['orderBy'];
  }

  /**
   * Extra .f-filters selects — filter field name => (value => label). The
   * current value is read off the same key in the filter state, so every
   * name here must also be an allow-listed key in the site's Filters.
   *
   * @return array<string,array<string,string>>
   */
  protected function filterOptions(): array
  {
    return [];
  }

  /**
   * The chrome's own wording. French by default — a site (or one component)
   * overrides the keys it wants to reword.
   */
  protected function adminText(string $key): string
  {
    return match ($key) {
      'notFound' => 'Élément introuvable.',
      'deleted' => 'Élément supprimé.',
      'deletedMany' => 'Éléments supprimés.',
      'saved' => 'Élément enregistré.',
      'created' => 'Élément créé.',
      'selectionPublished' => 'Sélection publiée.',
      'selectionUnpublished' => 'Sélection dépubliée.',
      'itemPublished' => 'Élément publié.',
      'itemUnpublished' => 'Élément dépublié.',
      'add' => 'Ajouter',
      'publish' => 'Publier la sélection',
      'unpublish' => 'Dépublier la sélection',
      'trash' => 'Supprimer la sélection',
      'selectionRequired' => 'Veuillez faire une sélection.',
      'confirmPublish' => 'Publier la sélection ?',
      'confirmUnpublish' => 'Dépublier la sélection ?',
      'confirmTrash' => 'Supprimer la sélection ?',
      'save' => 'Enregistrer et fermer',
      'apply' => 'Enregistrer',
      'cancel' => 'Annuler',
      'itemNew' => 'Nouveau',
      'itemEdit' => 'Modifier',
      'pagerPrev' => 'Page précédente',
      'pagerNext' => 'Page suivante',
      'colLabel' => 'Titre',
      'colPublished' => 'Publié',
      'colOrdering' => 'Ordre',
      'colId' => '#',
      default => $key,
    };
  }

  protected function renderItem(ResponseInterface $response, Form $form, ?int $id): ResponseInterface
  {
    $component = $this->component();
    // The framework form name — admin.js submits form[name='adminForm'].
    $form->setAction($this->adminBaseUrl() . '/' . $component . '/save/');

    $view = new FormView('adminForm');
    $view->setForm($form);

    $vData = [
      'component' => $component,
      'toolbar' => $this->buildItemToolbar($id)->toData(),
      'showFilterbar' => false,
      'filterbar' => null,
      'adminFormView' => $view->toData(),
    ];

    return $this->adminRender($response, $this->adminViewName('item'), $vData, $component, 'form');
  }

  protected function buildItemToolbar(?int $id): AdminToolbar
  {
    $component = $this->component();
    $base = $this->adminBaseUrl() . '/' . $component;
    $toolbar = new AdminToolbar($component);
    $toolbar->setTitle($this->title());
    $toolbar->setSubtitle($this->adminText($id ? 'itemEdit' : 'itemNew'));

    $toolbar->getToolbarButton('save', null, 'disk-saved')
      ->addStyle('btn-lg')
      ->setHref($base . '/save/')
      ->addDataAttr('task', 'save')
      ->setTip($this->adminText('save'));

    $toolbar->getToolbarButton('apply', null, 'ok')
      ->setHref($base . '/apply/')
      ->addDataAttr('task', 'apply')
      ->setTip($this->adminText('apply'));

    $toolbar->getToolbarButton('beforeCancel', 'divider');

    $toolbar->getToolbarButton('cancel', null, 'remove')
      ->setHref($base . '/cancel/')
      ->setTip($this->adminText('cancel'));

    return $toolbar;
  }

  protected function handleSave(ServerRequestInterface $request, ResponseInterface $response, bool $stay): ResponseInterface
  {
    $data = (array) $request->getParsedBody();
    $id = ((int) ($data['id'] ?? 0)) ?: null;

    $result = $this->service()->save($data, $id);

    if (!$result->isOk()) {
      $this->adminFlash(\implode(' ', $result->errors), 'error');
      $item = $id ? $this->service()->find($id) : null;

      return $this->renderItem($response->withStatus(422), $this->buildForm($item, $data), $id);
    }

    $this->adminFlash($this->adminText($id ? 'saved' : 'created'));

    if ($stay) {
      return $this->redirect($response, $this->adminBaseUrl() . '/' . $this->component() . '/edit/?id=' . $result->id);
    }

    return $this->redirect($response, $this->listUrl());
  }

  protected function togglePublished(ServerRequestInterface $request, ResponseInterface $response, int $to, string $message): ResponseInterface
  {
    $ids = $this->idsFromQuery($request);
    if ($ids) {
      $this->service()->setPublished($ids, $to);
      $this->adminFlash($message);
    }

    return $this->redirect($response, $this->listUrl());
  }

  /**
   * What publish()/unpublish() do, answered as JSON so the row can repaint
   * itself instead of the list reloading. `id` rides the POST body (csv
   * accepted, like the GET tasks); CsrfMiddleware has already vetted it.
   */
  private function jsonSetPublished(ServerRequestInterface $request, ResponseInterface $response, int $to): ResponseInterface
  {
    $raw = (string) (((array) $request->getParsedBody())['id'] ?? '');
    $ids = \array_values(\array_filter(\array_map('intval', \explode(',', $raw))));

    if (!$ids) {
      return $this->json($response, ['error' => $this->adminText('notFound')]);
    }

    $this->service()->setPublished($ids, $to);

    return $this->json($response, [
      'success' => true,
      'published' => $to,
      'message' => $this->adminText(1 === $to ? 'itemPublished' : 'itemUnpublished'),
    ]);
  }

  /** The toolbar selection links carry ?id=1,2,3 (admin.js rewrites hrefs). */
  protected function idsFromQuery(ServerRequestInterface $request): array
  {
    $raw = (string) ($request->getQueryParams()['id'] ?? '');

    return \array_values(\array_filter(\array_map('intval', \explode(',', $raw))));
  }

  protected function listUrl(): string
  {
    return $this->adminBaseUrl() . '/' . $this->component() . '/list/';
  }

  protected function redirect(ResponseInterface $response, string $url): ResponseInterface
  {
    return $response->withHeader('Location', $url)->withStatus(302);
  }

  protected function json(ResponseInterface $response, array $payload): ResponseInterface
  {
    $response->getBody()->write(\json_encode($payload));

    return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
  }
}
