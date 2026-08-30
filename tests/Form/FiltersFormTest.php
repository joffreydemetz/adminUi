<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\AdminUi\Tests\Form;

use JDZ\AdminUi\Form\FiltersForm;
use JDZ\AdminUi\ValueObject\Filterbar;
use JDZ\Form\FormData;
use PHPUnit\Framework\TestCase;

/**
 * The filterbar form, checked on the names and fieldsets jizy.admin.js and
 * Filterbar::fromFormData() bind to.
 *
 * @covers \JDZ\AdminUi\Form\FiltersForm
 */
class FiltersFormTest extends TestCase
{
  private const STATE = ['search' => '', 'orderBy' => 'ordering ASC', 'limit' => 25];

  private function build(array $state = self::STATE, bool $orderValid = true, array $filterOptions = [], bool $withSearch = false, string $baseUrl = '/admin'): array
  {
    $form = new FiltersForm('filters');
    $form->withBaseUrl($baseUrl)
      ->configure('things', '/admin/things/list/', 'csrf-token', $state, $orderValid, $filterOptions)
      ->withSearchbox($withSearch);

    $values = [
      'search' => $state['search'],
      'limit' => (string) $state['limit'],
      'component' => 'things',
      'listUrl' => '/admin/things/list/',
      '_csrf' => 'csrf-token',
    ];
    foreach (\array_keys($filterOptions) as $name) {
      $values[$name] = (string) ($state[$name] ?? '');
    }
    $form->init(new FormData($values));

    return $form->toData();
  }

  public function testTheActionIsTheSetFiltersEndpoint(): void
  {
    $data = $this->build();

    $this->assertSame('/admin/json/things/setFilters/', $data['attrs']['action']);
  }

  public function testTheBaseUrlIsConfigurable(): void
  {
    $data = $this->build(baseUrl: '/backoffice/');

    $this->assertSame('/backoffice/json/things/setFilters/', $data['attrs']['action']);
  }

  public function testTheStylesAdminJsBindsTo(): void
  {
    $classes = $this->build()['attrs']['class'];

    $this->assertStringContainsString('filters', $classes);
    $this->assertStringContainsString('orderAble', $classes);
    $this->assertStringContainsString('orderValid', $classes);
  }

  public function testAnInvalidOrderDropsTheDragClass(): void
  {
    $classes = $this->build(orderValid: false)['attrs']['class'];

    $this->assertStringContainsString('orderAble', $classes);
    $this->assertStringNotContainsString('orderValid', $classes);
  }

  public function testTheSearchboxIsOptIn(): void
  {
    $this->assertArrayNotHasKey('searchbox', $this->build()['fieldsets']);
    $this->assertArrayHasKey('searchbox', $this->build(withSearch: true)['fieldsets']);
  }

  public function testTheLimitSelectCarriesTheAllOption(): void
  {
    $limit = $this->build()['fieldsets']['sorting']['fields']['limit'];

    $options = [];
    foreach ($limit['field']['options'] as $option) {
      $options[$option['attrs']['value']] = $option['text'];
    }

    $this->assertSame([10, 25, 50, 0], \array_keys($options));
    $this->assertSame('Tous', $options[0]);
    $this->assertSame('filter[limit]', $limit['field']['attrs']['name']);
  }

  public function testTheHiddenFieldsAdminJsAndCsrfRead(): void
  {
    $hidden = [];
    foreach ($this->build()['fields'] as $field) {
      $hidden[$field['field']['attrs']['name']] = $field['field']['attrs']['value'];
    }

    $this->assertSame('things', $hidden['component']);
    $this->assertSame('/admin/things/list/', $hidden['listUrl']);
    $this->assertSame('csrf-token', $hidden['_csrf']);
  }

  public function testExtraFiltersLandInTheFiltersFieldsetAsSelects(): void
  {
    $data = $this->build(
      state: self::STATE + ['idCat' => '4'],
      filterOptions: ['idCat' => ['0' => 'Toutes', '4' => 'Sculptures']],
    );

    $field = $data['fieldsets']['filters']['fields']['idCat'];

    $this->assertSame('filter[idCat]', $field['field']['attrs']['name']);
    $this->assertSame('select', $field['field']['renderer']);

    $selected = [];
    foreach ($field['field']['options'] as $option) {
      if ($option['selected']) {
        $selected[] = $option['attrs']['value'];
      }
    }
    $this->assertSame(['4'], $selected);
  }

  public function testNoExtraFilterMeansNoFiltersFieldset(): void
  {
    $this->assertArrayNotHasKey('filters', $this->build()['fieldsets']);
  }

  public function testTheFilterbarValueObjectReadsTheWholeForm(): void
  {
    $bar = Filterbar::fromFormData($this->build(
      state: self::STATE + ['idCat' => '4'],
      filterOptions: ['idCat' => ['0' => 'Toutes', '4' => 'Sculptures']],
      withSearch: true,
    ))->toArray();

    $this->assertSame('filter[search]', $bar['searchbox']['field']['attrs']['name']);
    $this->assertSame('filter[limit]', $bar['limit']['field']['attrs']['name']);
    $this->assertCount(1, $bar['filters']);
    $this->assertCount(3, $bar['hidden']);
  }
}
