<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\AdminUi\Tests\Item;

use JDZ\AdminUi\Item\Item;
use JDZ\AdminUi\Item\ItemSection;
use JDZ\AdminUi\Item\ItemField;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Item::class)]
#[CoversClass(ItemSection::class)]
#[CoversClass(ItemField::class)]
class ItemTest extends TestCase
{

    public function testToDataNestsSectionsAndFieldsInTheOrderTheyWereAdded(): void
    {
        $item = new Item('preview');
        $general = $item->addSection('general', 'Général');
        $general->addField('email', ItemField::TYPE_EMAIL, 'Email', 'a@b.c');
        $general->addField('id', ItemField::TYPE_ID, 'ID', 12);
        $item->addSection('extra');

        $this->assertSame([
            'renderer' => 'item',
            'attrs' => [],
            'sections' => [
                [
                    'renderer' => 'item.section',
                    'attrs' => [],
                    'position' => 1,
                    'title' => 'Général',
                    'fields' => [
                        [
                            'renderer' => 'item.field',
                            'attrs' => ['class' => 'email'],
                            'position' => 1,
                            'name' => 'email',
                            'type' => 'email',
                            'label' => 'Email',
                            'value' => 'a@b.c',
                        ],
                        [
                            'renderer' => 'item.field',
                            'attrs' => ['class' => 'id'],
                            'position' => 2,
                            'name' => 'id',
                            'type' => 'id',
                            'label' => 'ID',
                            'value' => 12,
                        ],
                    ],
                ],
                [
                    'renderer' => 'item.section',
                    'attrs' => [],
                    'position' => 2,
                    'title' => '',
                    'fields' => [],
                ],
            ],
        ], $item->toData());
    }

    public function testAFieldCarriesItsTypeAndNameAsClasses(): void
    {
        $field = new ItemField('phone', ItemField::TYPE_PHONE, 'Téléphone', '01 02');

        $this->assertSame('phone', $field->toData()['attrs']['class']);

        $field = new ItemField('mobile', ItemField::TYPE_PHONE, 'Mobile');

        $this->assertSame('phone mobile', $field->toData()['attrs']['class']);
    }

    public function testHasSectionAndHasFieldKnowOnlyWhatWasAdded(): void
    {
        $item = new Item('preview');
        $item->addSection('general')->addField('email', ItemField::TYPE_EMAIL, 'Email');

        $this->assertTrue($item->hasSection('general'));
        $this->assertFalse($item->hasSection('billing'));
        $this->assertTrue($item->getSection('general')->hasField('email'));
        $this->assertFalse($item->getSection('general')->hasField('phone'));
    }

    public function testAnUnknownSectionThrows(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Element billing not found');

        (new Item('preview'))->getSection('billing');
    }

    public function testAnUnknownFieldThrows(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Element phone not found');

        (new ItemSection('general'))->getField('phone');
    }

    public function testASectionNameCanOnlyBeAddedOnce(): void
    {
        $item = new Item('preview');
        $item->addSection('general');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Element general already exists');

        $item->addSection('general');
    }

    public function testAFieldNameCanOnlyBeAddedOnce(): void
    {
        $section = new ItemSection('general');
        $section->addField('email', ItemField::TYPE_EMAIL, 'Email');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Element email already exists');

        $section->addField('email', ItemField::TYPE_TEXT, 'Courriel');
    }

    #[DataProvider('emptyValues')]
    public function testEmptyHiddenFlagsOnlyAnEmptyValue(mixed $value, string $type, bool $hidden): void
    {
        $field = (new ItemField('f', $type, 'F', $value))->withEmptyHidden();

        $this->assertSame($hidden, $field->toData()['hidden']);
    }

    public static function emptyValues(): array
    {
        return [
            'null' => ['value' => null, 'type' => ItemField::TYPE_TEXT, 'hidden' => true],
            'empty string' => ['value' => '', 'type' => ItemField::TYPE_TEXT, 'hidden' => true],
            'blank string' => ['value' => " \t\n", 'type' => ItemField::TYPE_TEXT, 'hidden' => true],
            'empty list' => ['value' => [], 'type' => ItemField::TYPE_LIST, 'hidden' => true],
            'text' => ['value' => 'x', 'type' => ItemField::TYPE_TEXT, 'hidden' => false],
            'zero string' => ['value' => '0', 'type' => ItemField::TYPE_TEXT, 'hidden' => false],
            'zero int' => ['value' => 0, 'type' => ItemField::TYPE_NUMBER, 'hidden' => false],
            'non-empty list' => ['value' => ['a'], 'type' => ItemField::TYPE_LIST, 'hidden' => false],
            'bool false' => ['value' => false, 'type' => ItemField::TYPE_BOOL, 'hidden' => false],
            'bool true' => ['value' => true, 'type' => ItemField::TYPE_BOOL, 'hidden' => false],
        ];
    }

    public function testWithoutEmptyHiddenAnEmptyFieldIsNotFlagged(): void
    {
        $this->assertArrayNotHasKey('hidden', (new ItemField('f', ItemField::TYPE_TEXT, 'F', ''))->toData());
        $this->assertArrayNotHasKey('hidden', (new ItemField('f', ItemField::TYPE_TEXT, 'F', ''))->withEmptyHidden(false)->toData());
    }
}