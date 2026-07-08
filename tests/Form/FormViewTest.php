<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\AdminUi\Tests\Form;

use JDZ\AdminUi\Form\FormView;
use JDZ\Form\Contract\FormInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JDZ\AdminUi\Form\FormView
 */
class FormViewTest extends TestCase
{
    private function stubForm(array $fieldsets): FormInterface
    {
        $form = $this->createStub(FormInterface::class);
        $form->method('toData')->willReturn([
            'attrs' => ['name' => 'stub'],
            'fields' => [],
            'fieldsets' => $fieldsets,
        ]);

        return $form;
    }

    public function testNameAndRenderer(): void
    {
        $view = new FormView('formations');

        $this->assertEquals('formations', $view->getName());
        $this->assertEquals('form.view', $view->getRenderer());
    }

    public function testToDataContract(): void
    {
        $view = (new FormView('formations'))->setForm($this->stubForm([
            ['name' => 'main', 'label' => '', 'description' => ''],
        ]));

        $data = $view->toData();

        $this->assertEquals('form.view', $data['renderer']);
        $this->assertArrayHasKey('form', $data);
        $this->assertArrayHasKey('fieldsets', $data['form']);
        $this->assertArrayHasKey('panels', $data);
        $this->assertEquals(
            ['name' => 'main', 'label' => '', 'description' => '', 'state' => 'force'],
            $data['panels'][0]
        );
    }

    public function testPanelStates(): void
    {
        $view = (new FormView('x'))->setForm($this->stubForm([
            ['name' => 'hidden-zone', 'label' => ''],
            ['name' => 'general', 'label' => 'General'],
            ['name' => 'seo', 'label' => 'SEO'],
        ]));

        $view->computePanelStates();

        // unlabeled -> force, first labeled -> active, rest -> closed
        $this->assertEquals('force', $view->getPanelState('hidden-zone'));
        $this->assertEquals('active', $view->getPanelState('general'));
        $this->assertEquals('closed', $view->getPanelState('seo'));
    }

    public function testPanelStatesAllUnlabeled(): void
    {
        $view = (new FormView('x'))->setForm($this->stubForm([
            ['name' => 'a', 'label' => ''],
            ['name' => 'b', 'label' => ''],
        ]));

        $view->computePanelStates();

        $this->assertEquals('force', $view->getPanelState('a'));
        $this->assertEquals('force', $view->getPanelState('b'));
    }

    public function testPanelStatesFirstFieldsetLabeled(): void
    {
        $view = (new FormView('x'))->setForm($this->stubForm([
            ['name' => 'general', 'label' => 'General'],
            ['name' => 'raw', 'label' => ''],
            ['name' => 'seo', 'label' => 'SEO'],
        ]));

        $view->computePanelStates();

        $this->assertEquals('active', $view->getPanelState('general'));
        $this->assertEquals('force', $view->getPanelState('raw'));
        $this->assertEquals('closed', $view->getPanelState('seo'));
    }

    public function testSetPanelStateOverrideSurvivesToData(): void
    {
        $view = (new FormView('x'))->setForm($this->stubForm([
            ['name' => 'general', 'label' => 'General'],
            ['name' => 'seo', 'label' => 'SEO'],
        ]));

        $view->computePanelStates();
        $view->setPanelState('seo', 'active');

        $states = [];
        foreach ($view->toData()['panels'] as $panel) {
            $states[$panel['name']] = $panel['state'];
        }

        $this->assertEquals('active', $states['general']);
        $this->assertEquals('active', $states['seo']);
    }
}
