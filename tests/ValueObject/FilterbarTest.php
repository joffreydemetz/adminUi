<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\AdminUi\Tests\ValueObject;

use JDZ\AdminUi\ValueObject\Filterbar;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Filterbar::class)]
class FilterbarTest extends TestCase
{
    private function formToData(): array
    {
        return [
            'attrs' => ['name' => 'filters', 'method' => 'GET'],
            'fields' => [['name' => 't', 'value' => 'abc']],
            'fieldsets' => [
                'searchbox' => ['fields' => ['search' => ['name' => 'search', 'value' => '']]],
                'sorting' => ['fields' => [
                    'limit' => ['name' => 'limit', 'value' => 20],
                    'orderBy' => ['name' => 'orderBy', 'value' => 'a.id ASC'],
                ]],
                'filters' => ['fields' => [
                    'published' => ['name' => 'published'],
                    'category' => ['name' => 'category'],
                ]],
            ],
        ];
    }

    public function testFromFormDataMapping(): void
    {
        $bar = Filterbar::fromFormData($this->formToData());

        $this->assertEquals(['name' => 'filters', 'method' => 'GET'], $bar->attrs);
        $this->assertEquals([['name' => 't', 'value' => 'abc']], $bar->hidden);
        $this->assertEquals(['name' => 'search', 'value' => ''], $bar->searchbox);
        $this->assertEquals(['name' => 'limit', 'value' => 20], $bar->limit);
        $this->assertEquals(['name' => 'orderBy', 'value' => 'a.id ASC'], $bar->orderby);
        // filters keep order, keys dropped
        $this->assertEquals([['name' => 'published'], ['name' => 'category']], $bar->filters);
    }

    public function testFromFormDataMissingFieldsets(): void
    {
        $bar = Filterbar::fromFormData(['attrs' => [], 'fields' => []]);

        $this->assertNull($bar->searchbox);
        $this->assertNull($bar->limit);
        $this->assertNull($bar->orderby);
        $this->assertEquals([], $bar->filters);
    }

    public function testToArrayKeys(): void
    {
        $arr = Filterbar::fromFormData($this->formToData())->toArray();

        $this->assertEquals(
            ['attrs', 'hidden', 'searchbox', 'limit', 'orderby', 'filters'],
            array_keys($arr)
        );
    }

    public function testFromLegacyArrayRoundtrip(): void
    {
        $arr = Filterbar::fromFormData($this->formToData())->toArray();
        $roundtripped = Filterbar::fromLegacyArray($arr)->toArray();

        $this->assertEquals($arr, $roundtripped);
    }

    public function testWithAddedFilterAppends(): void
    {
        $bar = (new Filterbar())
            ->withAddedFilter(['name' => 'published'])
            ->withAddedFilter(['name' => 'category']);

        $this->assertEquals([['name' => 'published'], ['name' => 'category']], $bar->filters);
    }

    #[DataProvider('withers')]
    public function testEveryWitherChangesOnlyItsOwnKeyOnACopy(string $wither, string $key, ?array $value): void
    {
        $bar = Filterbar::fromFormData($this->formToData());
        $before = $bar->toArray();

        $changed = $bar->{$wither}($value);

        $expected = $before;
        $expected[$key] = $value;
        $this->assertSame($expected, $changed->toArray());
        $this->assertSame($before, $bar->toArray());
    }

    public static function withers(): array
    {
        return [
            'attrs' => ['wither' => 'withAttrs', 'key' => 'attrs', 'value' => ['id' => 'f']],
            'hidden' => ['wither' => 'withHidden', 'key' => 'hidden', 'value' => []],
            'searchbox off' => ['wither' => 'withSearchbox', 'key' => 'searchbox', 'value' => null],
            'limit' => ['wither' => 'withLimit', 'key' => 'limit', 'value' => ['name' => 'limit', 'value' => 50]],
            'orderby off' => ['wither' => 'withOrderBy', 'key' => 'orderby', 'value' => null],
            'filters' => ['wither' => 'withFilters', 'key' => 'filters', 'value' => [['name' => 'idCat']]],
        ];
    }
}
