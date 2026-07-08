<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\AdminUi\Tests\ValueObject;

use JDZ\AdminUi\ValueObject\Filterbar;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JDZ\AdminUi\ValueObject\Filterbar
 */
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

    public function testWithersAreImmutable(): void
    {
        $bar = new Filterbar(['a' => 1]);
        $new = $bar->withAttrs(['b' => 2]);

        $this->assertNotSame($bar, $new);
        $this->assertEquals(['a' => 1], $bar->attrs);
        $this->assertEquals(['b' => 2], $new->attrs);
    }

    public function testWithAddedFilterAppends(): void
    {
        $bar = (new Filterbar())
            ->withAddedFilter(['name' => 'published'])
            ->withAddedFilter(['name' => 'category']);

        $this->assertEquals([['name' => 'published'], ['name' => 'category']], $bar->filters);
    }
}
