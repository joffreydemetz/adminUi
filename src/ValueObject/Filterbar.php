<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\AdminUi\ValueObject;

/**
 * Filterbar represents the filter bar configuration for list views.
 * Replaces the ad-hoc stdClass $filterbar currently used in AdminModelListTrait::list().
 * 
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class Filterbar
{
  public function __construct(
    public array $attrs = [],
    public array $hidden = [],
    public ?array $searchbox = null,
    public ?array $limit = null,
    public ?array $orderby = null,
    public array $filters = []
  ) {}

  /**
   * Create a Filterbar from form data array
   */
  public static function fromFormData(array $formToData): static
  {
    $data = $formToData['fieldsets']['filters']['fields'] ?? [];
    
    $filters = [];
    foreach ($data as $field) {
      $filters[] = $field;
    }
    
    return new static(
      $formToData['attrs'] ?? [],
      $formToData['fields'] ?? [],
      $formToData['fieldsets']['searchbox']['fields']['search'] ?? null,
      $formToData['fieldsets']['sorting']['fields']['limit'] ?? null,
      $formToData['fieldsets']['sorting']['fields']['orderBy'] ?? null,
      $filters
    );
  }

  /**
   * Create a Filterbar from the current framework format
   */
  public static function fromLegacyArray(array $legacyData): static
  {
    return new static(
      $legacyData['attrs'] ?? [],
      $legacyData['hidden'] ?? [],
      $legacyData['searchbox'] ?? null,
      $legacyData['limit'] ?? null,
      $legacyData['orderby'] ?? null,
      $legacyData['filters'] ?? []
    );
  }

  /**
   * Convert to array for view data
   */
  public function toArray(): array
  {
    return [
      'attrs' => $this->attrs,
      'hidden' => $this->hidden,
      'searchbox' => $this->searchbox,
      'limit' => $this->limit,
      'orderby' => $this->orderby,
      'filters' => $this->filters,
    ];
  }

  /**
   * Set the attributes
   */
  public function withAttrs(array $attrs): static
  {
    $new = clone $this;
    $new->attrs = $attrs;
    return $new;
  }

  /**
   * Set the hidden fields
   */
  public function withHidden(array $hidden): static
  {
    $new = clone $this;
    $new->hidden = $hidden;
    return $new;
  }

  /**
   * Set the searchbox
   */
  public function withSearchbox(?array $searchbox): static
  {
    $new = clone $this;
    $new->searchbox = $searchbox;
    return $new;
  }

  /**
   * Set the limit field
   */
  public function withLimit(?array $limit): static
  {
    $new = clone $this;
    $new->limit = $limit;
    return $new;
  }

  /**
   * Set the orderby field
   */
  public function withOrderBy(?array $orderby): static
  {
    $new = clone $this;
    $new->orderby = $orderby;
    return $new;
  }

  /**
   * Set the filters
   */
  public function withFilters(array $filters): static
  {
    $new = clone $this;
    $new->filters = $filters;
    return $new;
  }

  /**
   * Add a filter
   */
  public function withAddedFilter(array $filter): static
  {
    $new = clone $this;
    $new->filters[] = $filter;
    return $new;
  }
}