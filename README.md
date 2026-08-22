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
| `JDZ\AdminUi\Controller` | `MediasControllerTrait`, `PsrUploadedFileBridge` — the shared /nimda media manager (opt-in, see below) |

## Scope

This package is the **rendering layer** only. Data access (queries, repositories)
and the app lifecycle stay in the consuming framework. A list query builder, for
example, is *not* part of this package.

The one exception is `JDZ\AdminUi\Controller\` — shared admin *behaviour*, for
the screens where six back offices would otherwise grow six copies of the same
file. It is opt-in: nothing else in the package touches HTTP, and the extra
dependencies are `suggest`ed rather than required.

## The media manager

`MediasControllerTrait` is the whole /nimda media manager: browse, create /
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
    use JDZ\AdminUi\Controller\MediasControllerTrait;

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

Routes (the paths `JiZy.makeUrl()` builds — override `mediaTaskUrl()` to move
them): `json/mediamanager/{init,fs,upload}`,
`json/mediamanager/folder/{create,rename,move,delete}`,
`json/mediamanager/file/{infos,rename,move,delete,protect,unprotect}`,
`json/medias/selector`, plus `medias` and `medias/download`. The `create`,
`rename` and `move` folder/file paths answer a GET with the dialog and a POST
with the operation.

`PsrUploadedFileBridge` turns a PSR-7 upload into the HttpFoundation one the
uploader takes. It hands over the mime **detected from the bytes**, not the one
the browser declared, and the trait additionally checks the stored extension
against the configured whitelist — an upload endpoint inside the web root gets
both.

## Install

```
composer require jdz/adminui
```
