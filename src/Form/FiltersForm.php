<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace JDZ\AdminUi\Form;

use JDZ\Form\Field\Hidden as HiddenField;
use JDZ\Form\Field\Search;
use JDZ\Form\Field\Select;
use JDZ\Form\Form;
use JDZ\Form\FormRow\Hidden as HiddenRow;
use JDZ\Form\SelectFieldOption;

/**
 * The list filterbar form — fieldset names (searchbox / sorting / filters) and
 * field names (search / limit) are the exact keys
 * JDZ\AdminUi\ValueObject\Filterbar::fromFormData() extracts, and the
 * filter[...] prefix + hidden component/listUrl fields are what
 * jizy.admin.js's submitFilters/ordering wiring binds to.
 *
 * Everything in the `filters` fieldset lands in the .f-filters block, which
 * header.tmpl renders with field_select unconditionally — extra filters must
 * therefore always be selects.
 *
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class FiltersForm extends Form
{
  private string $component = '';
  private string $listUrl = '';
  private string $csrfValue = '';
  private string $baseUrl = '/admin';
  /** @var array{search:string, orderBy:string, limit:int} */
  private array $state = ['search' => '', 'orderBy' => 'ordering ASC', 'limit' => 25];
  private bool $orderValid = true;
  /** @var array<string,array<string,string>> field name => (value => label) */
  private array $filterOptions = [];

  /**
   * The searchbox is opt-in: most of these lists are one page long, and
   * the framework's `code:` search vocabulary was never ported, so the
   * box promised more than it did. AdminControllerTrait::hasSearch() flips it.
   */
  private bool $withSearch = false;

  /**
   * The /admin prefix the setFilters action hangs off — call it before
   * configure(), which is what builds the action.
   */
  public function withBaseUrl(string $baseUrl): static
  {
    $this->baseUrl = \rtrim($baseUrl, '/');

    return $this;
  }

  public function configure(string $component, string $listUrl, string $csrf, array $state, bool $orderValid = true, array $filterOptions = []): static
  {
    $this->component = $component;
    $this->listUrl = $listUrl;
    $this->csrfValue = $csrf;
    $this->state = $state + $this->state;
    $this->orderValid = $orderValid;
    $this->filterOptions = $filterOptions;

    $this->setAction($this->baseUrl . '/json/' . $component . '/setFilters/');
    // filters is the .ca-f-menu toggle's slide target (form.filters);
    // orderAble opens the inline order-form wiring; orderValid enables
    // Sortable drag — all read by admin.js off `.ca-filterbar form`.
    $this->addStyle('filters');
    $this->addStyle('orderAble');
    if ($orderValid) {
      $this->addStyle('orderValid');
    }

    return $this;
  }

  public function withSearchbox(bool $withSearch = true): static
  {
    $this->withSearch = $withSearch;

    return $this;
  }

  protected function populate(): void
  {
    if ($this->withSearch) {
      $this->makeFormFieldset('searchbox');

      $search = $this->makeFormRow('search', 'searchbox');
      $search->setPrefix('filter');
      $search->withLabel(false);
      $search->setField(new Search('search'));
      $search->setValue($this->state['search']);
    }

    $sorting = $this->makeFormFieldset('sorting');

    $limit = $this->makeFormRow('limit', 'sorting');
    $limit->setPrefix('filter');
    $limit->withLabel(false);
    $limitField = new Select('limit');
    foreach ([10, 25, 50, 0] as $n) {
      $limitField->addOption(new SelectFieldOption((string) $n, 0 === $n ? 'Tous' : (string) $n, $n === $this->state['limit']));
    }
    $limit->setField($limitField);
    $limit->setValue((string) $this->state['limit']);

    // Extra filter selects (.f-filters). The fieldset name is explicit on
    // purpose — addFormRow() defaults to `main`, which this form never has.
    if ($this->filterOptions) {
      $this->makeFormFieldset('filters');

      foreach ($this->filterOptions as $name => $options) {
        $row = $this->makeFormRow($name, 'filters');
        $row->setPrefix('filter');
        $row->withLabel(false);
        $field = new Select($name);
        $current = (string) ($this->state[$name] ?? '');
        foreach ($options as $value => $text) {
          $field->addOption(new SelectFieldOption((string) $value, $text, (string) $value === $current));
        }
        $row->setField($field);
        $row->setValue($current);
      }
    }

    // Unprefixed hidden fields — read directly by the admin JS
    // (input[name='component'], input[name='listUrl']) and CsrfMiddleware.
    foreach ([
      'component' => $this->component,
      'listUrl' => $this->listUrl,
      '_csrf' => $this->csrfValue,
    ] as $name => $value) {
      $row = new HiddenRow($name);
      $row->setField(new HiddenField($name));
      $row->setValue($value);
      $this->addFormRow($row);
    }
  }
}
