# jdz/adminUi

Framework-agnostic admin UI rendering primitives.

Value objects that serialize (via `toData()`) to the JSON consumed by the JS
admin bundle — no HTTP, no database, no app lifecycle. Built on
[`jdz/htmlrenderer`](https://jdz.joffreydemetz.com/htmlrenderer).

## Contents

| Namespace | Classes |
|---|---|
| `JDZ\AdminUi\List` | `Columns`, `Column`, `Triggers`, `Trigger`, `ItemActions`, `Row`, `RowInterface` |
| `JDZ\AdminUi\Toolbar` | `Toolbar`, `ToolbarButton`, `AdminToolbar` |
| `JDZ\AdminUi\Item` | `Item`, `ItemSection`, `ItemField`, `ItemTable` — read-only detail views |
| `JDZ\AdminUi\Form` | `FormView` (wraps a [`jdz/form`](https://jdz.joffreydemetz.com/form) form with fieldset panel states), `FormActions` |
| `JDZ\AdminUi\ValueObject` | `ModalChrome`, `Filterbar` |
| `JDZ\AdminUi\Contract` | `AdminServiceInterface`, `ListState`, `SaveResult` — what an entity admin asks of the app |
| `JDZ\AdminUi\Controller\Traits` | `AdminControllerTrait` — the shared entity admin; `MediasControllerTrait` — the shared media manager (both opt-in, see below) |
| `JDZ\AdminUi\Controller` | `PsrUploadedFileBridge` — the PSR-7 → HttpFoundation uploaded-file adapter the media manager needs |
| `JDZ\AdminUi` | `IconPrefix` — which icon vocabulary the toolbars and triggers paint (see below) |

## Scope

This package is the **rendering layer** only. Data access (queries, repositories)
and the app lifecycle stay in the consuming framework. A list query builder, for
example, is *not* part of this package.

The one exception is `JDZ\AdminUi\Controller\` — shared admin *behaviour*, for
the screens where six back offices would otherwise grow six copies of the same
file. It is opt-in: nothing else in the package touches HTTP, and the extra
dependencies are `suggest`ed rather than required.

## Upgrading to 2.1.0

Nothing breaks; one seam is added. The shared screens are now named through
`adminViewName()` (entity admin) and `mediaView()` (library) instead of literal
strings, and both still default to what 2.0.0 rendered:

```php
protected function adminViewName(string $name): string   // 'items', 'item'
{
  return 'views/admin/' . $name . '.tmpl';
}

protected function mediaView(string $name): string       // 'display', 'selector', …
{
  return 'views/admin/medias/' . $name . '.tmpl';
}
```

A site whose templates live under `templates/admin/` overrides one line each.
The medias controller carries its own copy because it is standalone — it does
not use `AdminControllerTrait`.

The defaults become `views/<name>.twig` in 3.0.0.

## Upgrading to 2.0.0

Two breaking changes, both one-line fixes:

- The two controller traits moved to `JDZ\AdminUi\Controller\Traits\` — update
  the `use` lines. The old names still resolve (as deprecated traits that
  compose the new ones) so an upgrade need not be atomic; they go in 2.1.0.
- `adminBaseUrl()` and the new `mediaBaseUrl()` both default to `/admin`.
  A site served from another prefix must override them — the package no longer
  assumes one.

## Icons

Toolbar buttons and row triggers take a bare icon name (`'plus'`, `'edit'`) and
render it as a CSS class. Which class is the admin's business, not the package's:

```php
use JDZ\AdminUi\IconPrefix;

IconPrefix::use('icon icon-');   // -> class="icon icon-plus"
```

The default is `glyphicons glyphicons-`, which is what every consumer painted
before this was configurable — so an admin that says nothing keeps exactly the
markup it had. Set it once at bootstrap, before the first toolbar is built.

## The entity admin

`JDZ\AdminUi\Controller\Traits\AdminControllerTrait` is one back-office component —
list, form, publish, delete, reorder, filterbar — as a single implementation.
It owns the whole callisto task vocabulary
(`list add edit save apply cancel publish unpublish trash changeorder
setFilters resetFilters` plus the `json` publish/unpublish pair) and every
value object the list screen needs.

It splits into three questions.

**What the component is** — four hooks a controller must answer: its slug, its
title, how one row paints (`buildRow`), and what its form looks like
(`buildForm`).

**Where the data lives** — `service()`, returning a
`JDZ\AdminUi\Contract\AdminServiceInterface`. That is the whole data contract:

```php
public function count(ListState $state): int;
public function list(ListState $state): array;
public function find(int $id): ?object;
public function save(array $input, ?int $id): SaveResult;
public function setPublished(array $ids, int $published): void;
public function delete(array $ids): void;
public function moveOrdering(int $id, int $newValue): void;
```

The controller never sees a query. `ListState` is the filterbar window
(`limit`, `offset`, `search`, `orderBy`, plus whatever extra selects the site
declared — `$state->filter('idCat')` casts one to the type of its default);
`SaveResult` is `ok($id)` or `fail(['title' => 'Titre requis.'])`, and a
failure is what turns a save into a 422 re-render of the posted values instead
of a redirect. Validation and business rules live in the service, which is the
only layer that knows how a row is stored.

**How the site's chrome works** — six `admin*` hooks (`adminRender`,
`adminFlash`, `adminCsrfToken`, `adminFilterState`, `adminFiltersSet`,
`adminFiltersReset`), the same shape `MediasControllerTrait` uses for its
`mediaRender*` / `mediaState*` pair. Answer them once, in an abstract site-side
`AdminController`, and every component extends that:

```php
abstract class AdminController                    // the site, once
{
  use JDZ\AdminUi\Controller\Traits\AdminControllerTrait;

  public function __construct(
    protected AdminRenderer $renderer,
    protected Session $session,
    protected Filters $filters,
  ) {}

  protected function adminRender(ResponseInterface $r, string $view, array $vData, string $c, string $task): ResponseInterface
  {
    return $this->renderer->render($r, $view, $vData, $c, $task);
  }

  protected function adminFlash(string $message, string $type = 'success'): void { $this->renderer->flash($message, $type); }
  protected function adminCsrfToken(): string { return $this->session->csrfToken(); }
  protected function adminFilterState(string $c): array { return $this->filters->for($c); }
  protected function adminFiltersSet(string $c, array $filter): void { $this->filters->set($c, $filter); }
  protected function adminFiltersReset(string $c): void { $this->filters->reset($c); }
}

final class FormationsController extends AdminController   // one component
{
  public function __construct(AdminRenderer $r, Session $s, Filters $f, private FormationService $service)
  {
    parent::__construct($r, $s, $f);
  }

  protected function component(): string { return 'formations'; }
  protected function title(): string { return 'Formations'; }
  protected function service(): AdminServiceInterface { return $this->service; }
  protected function buildRow(object $item, bool $orderValid): Row { … }
  protected function buildForm(?object $item, array $input = []): Form { … }
}
```

Hold the concrete service in the controller, not the interface — a container
cannot instantiate an interface.

Everything else has a default worth keeping: `buildColumns()` paints the usual
checkbox / label / published / ordering / id / triggers set,
`adminBaseUrl()` is `/admin`, `adminViewName()` points at
`views/admin/<name>.tmpl`, `hasSearch()` is off, `filterOptions()` is empty,
`orderingSort()` is `'ordering ASC'` (an admin listing newest first returns
`'ordering DESC'` and drag-reorder follows), and `adminText()` holds the French
wording of every flash, tooltip and confirm — override the keys a site wants to
reword.

`JDZ\AdminUi\Form\FiltersForm` is the filterbar form the trait builds: the
searchbox, the limit select, and one select per declared filter, under the
exact fieldset and field names `Filterbar::fromFormData()` and the admin
bundle read.

### The JS contract, which must not drift

`jizy.admin.js` rewrites the toolbar hrefs with `?id=1,2,3`, posts the
filterbar to `json/{component}/setFilters/`, expects `{success:true}` back from
`changeorder`, and reads the list view data off `ordervalid`, `orderDir`,
`listHeaders`, `items`, `pagination` and `filterbar.filterActive`. The trait
exists to keep exactly that shape in one place.

## The media manager

`MediasControllerTrait` is the whole /admin media manager: browse, create /
rename / move / delete folders and files, upload, watermark, and the image
picker a form field opens. The filesystem work is
[`jdz/mediamanager`](https://jdz.joffreydemetz.com/mediamanager); the browser is
`mediamanager.js` from the admin bundle; this is the adapter between them, and
it emits exactly the JSON that JS already expects.

```
composer require jdz/mediamanager
```

A consuming controller supplies the per-site parts and nothing else:

```php
final class MediasController extends AdminController
{
    use JDZ\AdminUi\Controller\Traits\MediasControllerTrait;

    protected function mediaConfig(): MediaConfig { /* where the library lives */ }
    protected function mediaTranslate(string $key, array $params = []): string { … }
    protected function mediaRenderFragment(string $view, array $vData): string { … }
    protected function mediaRenderPage(ResponseInterface $r, string $v, array $d): ResponseInterface { … }
    protected function mediaCsrfToken(): string { … }
    protected function mediaStateLoad(): array { … }
    protected function mediaStateSave(array $state): void { … }
    protected function mediaThumbUrl(string $relPath, int $width): string { … }
    protected function mediaSelectorImage(string $relPath, int $width): ?array { … }
}
```

Routes (the paths `JiZy.makeUrl()` builds — they hang off `mediaBaseUrl()`,
which defaults to `/admin`; override that one method to serve the back office
somewhere else, or `mediaTaskUrl()` / `mediaDownloadUrl()` to reshape a single
URL). A medias controller does not inherit `adminBaseUrl()`: it is standalone,
so a site that moves its admin overrides both hooks — and likewise
`mediaView()` alongside `adminViewName()` when it renames its templates.
`json/mediamanager/{init,fs,upload}`,
`json/mediamanager/folder/{create,rename,move,delete}`,
`json/mediamanager/file/{infos,rename,move,delete,protect,unprotect}`,
`json/medias/selector`, plus `medias` and `medias/download`. The `create`,
`rename` and `move` folder/file paths answer a GET with the dialog and a POST
with the operation.

### The picker

`json/medias/selector` renders the modal `JiZy.Admin.mediaPicker` opens — on a
form's image field, and on the rich-text editor's image button. It always ships
the folder list alongside the tree, so the picker can switch folders without
going through the media manager screen, and `json/mediamanager/folder/create`
accepts a posted `parent` for the same reason: the picker creates a folder where
*it* is looking, not where the session last left the media screen. The created
folder comes back in `folder` so the caller can reload straight into it.

`?mode=editor` adds the caption / alt / anchoring fieldset the editor needs to
build its `<figure>`. Re-opening the picker on an existing figure passes `src`,
`alt`, `caption` and `align` back in and they arrive prefilled — `align` is
whitelisted against `MEDIA_ALIGNMENTS` rather than echoed, since it is query
string on its way into a view.

```
GET json/medias/selector?folder=blog
GET json/medias/selector?mode=editor&src=media/blog/x.jpg&alt=…&caption=…&align=right
POST json/mediamanager/folder/create   m[parent]=blog&m[folderName]=2026
```

`PsrUploadedFileBridge` turns a PSR-7 upload into the HttpFoundation one the
uploader takes. It hands over the mime **detected from the bytes**, not the one
the browser declared, and the trait additionally checks the stored extension
against the configured whitelist — an upload endpoint inside the web root gets
both.

## Install

```
composer require jdz/adminui
```
