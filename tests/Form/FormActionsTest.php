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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Documents the current FormActions behavior (baked /admin/{component}/* hrefs —
 * consumers must rewrite them through their router before display).
 */
#[CoversClass(FormActions::class)]
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

    #[DataProvider('cancelContexts')]
    public function testTheCancelActionAdaptsToTheContext(bool $isUpdate, string $type, string $task, string $tip): void
    {
        $button = $this->actions()->createCancelAction('pages', $isUpdate, 'Annuler', 'Fermer');

        $data = $button->toData();

        $this->assertSame($type, $data['type']);
        $this->assertSame($task, $data['attrs']['data-task']);
        $this->assertSame($tip, $data['attrs']['title']);
        $this->assertSame('/admin/pages/cancel', $data['attrs']['href']);
        $this->assertSame('btn btn-lg', $data['attrs']['class']);
        $this->assertSame('<span class="glyphicons glyphicons-remove-circle"></span> ', $data['text']);
    }

    public static function cancelContexts(): array
    {
        return [
            'editing an item' => ['isUpdate' => true, 'type' => 'cancel', 'task' => 'cancel', 'tip' => 'Annuler'],
            'creating an item' => ['isUpdate' => false, 'type' => 'close', 'task' => 'close', 'tip' => 'Fermer'],
        ];
    }

    public function testSaveAndApplyTakeTheirTipAndIcon(): void
    {
        $actions = $this->actions();

        $save = $actions->createSaveAction('pages', 'Enregistrer et fermer', 'floppy')->toData();
        $apply = $actions->createApplyAction('pages', 'Enregistrer', 'disk')->toData();

        $this->assertSame(['Enregistrer et fermer', '<span class="glyphicons glyphicons-floppy"></span> '], [$save['attrs']['title'], $save['text']]);
        $this->assertSame(['Enregistrer', '<span class="glyphicons glyphicons-disk"></span> '], [$apply['attrs']['title'], $apply['text']]);
        $this->assertSame('/admin/pages/apply', $apply['attrs']['href']);
    }

    public function testTheActionsLandOnTheWrappedToolbarInOrder(): void
    {
        $toolbar = new AdminToolbar('toolbar');
        (new FormActions($toolbar))->createStandardActions('pages', true);

        $this->assertSame(
            ['save', 'apply', 'beforeCancel', 'cancel'],
            \array_column($toolbar->toData()['buttons'], 'name'),
        );
    }
}
