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

## Scope

This package is the **rendering layer** only. Data access (queries, repositories)
and the app lifecycle stay in the consuming framework. A list query builder, for
example, is *not* part of this package.

## Install

```
composer require jdz/adminui
```
