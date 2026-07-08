<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\AdminUi\Tests\ValueObject;

use JDZ\AdminUi\ValueObject\ModalChrome;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JDZ\AdminUi\ValueObject\ModalChrome
 */
class ModalChromeTest extends TestCase
{
    public function testDefaults(): void
    {
        $chrome = new ModalChrome();

        $this->assertEquals('', $chrome->title);
        $this->assertEquals(ModalChrome::SIZE_MD, $chrome->size);
        $this->assertFalse($chrome->noheader);
        $this->assertTrue($chrome->closeIcon);
    }

    public function testFromArrayFull(): void
    {
        $chrome = ModalChrome::fromArray([
            'title' => 'Preview',
            'size' => ModalChrome::SIZE_SM,
            'noheader' => true,
            'closeIcon' => false,
        ]);

        $this->assertEquals('Preview', $chrome->title);
        $this->assertEquals('sm', $chrome->size);
        $this->assertTrue($chrome->noheader);
        $this->assertFalse($chrome->closeIcon);
    }

    public function testFromArrayPartialUsesDefaults(): void
    {
        $chrome = ModalChrome::fromArray(['title' => 'Note']);

        $this->assertEquals('Note', $chrome->title);
        $this->assertEquals(ModalChrome::SIZE_MD, $chrome->size);
        $this->assertFalse($chrome->noheader);
        $this->assertTrue($chrome->closeIcon);
    }

    public function testToArrayRoundtrip(): void
    {
        $arr = ['title' => 'X', 'size' => 'lg', 'noheader' => true, 'closeIcon' => true];

        $this->assertEquals($arr, ModalChrome::fromArray($arr)->toArray());
    }

    public function testWithersAreImmutable(): void
    {
        $chrome = new ModalChrome('One');
        $new = $chrome->withTitle('Two')->withSize(ModalChrome::SIZE_XL)->withNoHeader()->withCloseIcon(false);

        $this->assertEquals('One', $chrome->title);
        $this->assertEquals(ModalChrome::SIZE_MD, $chrome->size);
        $this->assertEquals('Two', $new->title);
        $this->assertEquals('xl', $new->size);
        $this->assertTrue($new->noheader);
        $this->assertFalse($new->closeIcon);
    }
}
