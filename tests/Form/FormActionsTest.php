<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\AdminUi\Tests\Form;

use JDZ\AdminUi\Form\FormActions;
use JDZ\AdminUi\Toolbar\AdminToolbar;
use PHPUnit\Framework\TestCase;

/**
 * Documents the current FormActions behavior (baked /admin/{component}/* hrefs —
 * consumers must rewrite them through their router before display).
 *
 * @covers \JDZ\AdminUi\Form\FormActions
 */
class FormActionsTest extends TestCase
{
    private function actions(): FormActions
    {
        return new FormActions(new AdminToolbar('toolbar'));
    }

    public function testCreateStandardActionsUpdate(): void
    {
        $buttons = $this->actions()->createStandardActions('formations', true);

        $this->assertEquals(['save', 'apply', 'divider', 'cancel'], array_keys($buttons));

        $save = $buttons['save']->toData();
        $this->assertEquals('/admin/formations/save', $save['attrs']['href']);
        $this->assertStringContainsString('btn-lg', implode(' ', (array)$save['attrs']['class']));

        $this->assertEquals('divider', $buttons['divider']->getType());

        // update context: plain cancel (no close type)
        $this->assertEquals('cancel', $buttons['cancel']->getType());
    }

    public function testCreateStandardActionsNew(): void
    {
        $buttons = $this->actions()->createStandardActions('formations', false);

        // new-item context: cancel becomes a close button with data-task=close
        $cancel = $buttons['cancel'];
        $this->assertEquals('close', $cancel->getType());

        $data = $cancel->toData();
        $this->assertEquals('close', $data['attrs']['data-task'] ?? null);
    }

    public function testCreateDivider(): void
    {
        $divider = $this->actions()->createDivider('sep');

        $this->assertEquals('divider', $divider->getType());
        $this->assertEquals('sep', $divider->getName());
    }

    public function testCreateSaveAndApplyActions(): void
    {
        $buttons = $this->actions()->createSaveAndApplyActions('pages');

        $this->assertEquals(['save', 'apply'], array_keys($buttons));
        $this->assertEquals('/admin/pages/save', $buttons['save']->toData()['attrs']['href']);
        $this->assertEquals('/admin/pages/apply', $buttons['apply']->toData()['attrs']['href']);
    }
}
