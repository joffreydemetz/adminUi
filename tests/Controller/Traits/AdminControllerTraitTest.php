<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\AdminUi\Tests\Controller\Traits;

use JDZ\AdminUi\Contract\AdminServiceInterface;
use JDZ\AdminUi\Contract\ListState;
use JDZ\AdminUi\Contract\SaveResult;
use JDZ\AdminUi\Controller\Traits\AdminControllerTrait;
use JDZ\Form\Field\Hidden as HiddenField;
use JDZ\Form\Field\Text;
use JDZ\Form\Form;
use JDZ\AdminUi\List\Row;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

/**
 * The trait driven the way jizy.admin.js drives it, over an in-memory service.
 */
#[CoversTrait(AdminControllerTrait::class)]
#[CoversClass(ListState::class)]
#[CoversClass(SaveResult::class)]
class AdminControllerTraitTest extends TestCase
{
  private FakeAdminService $service;
  private TestAdminController $controller;

  protected function setUp(): void
  {
    $this->service = new FakeAdminService();
    $this->controller = new TestAdminController($this->service);
  }

  // ---------------------------------------------------------------- list --

  public function testListShapesTheVDataAdminJsReads(): void
  {
    $this->controller->list($this->get(), new Response());

    $vData = $this->controller->lastVData;

    $this->assertSame('views/admin/items.tmpl', $this->controller->lastView);
    $this->assertSame('things', $this->controller->lastComponent);
    $this->assertSame('list', $this->controller->lastTask);

    $this->assertTrue($vData['ordervalid']);
    $this->assertSame('ASC', $vData['orderDir']);
    $this->assertSame(3, $vData['total']);
    $this->assertCount(3, $vData['items']);
    $this->assertNull($vData['pagination']);
    $this->assertSame(
      ['checkbox', 'label', 'published', 'ordering', 'id', 'triggers'],
      \array_column($vData['listHeaders']['columns'], 'name'),
    );
    $this->assertFalse($vData['filterbar']['filterActive']);
    $this->assertFalse($vData['showFilterbar']);
  }

  public function testListPassesTheWholeWindowToTheService(): void
  {
    $this->controller->state = ['search' => 'yoga', 'orderBy' => 'ordering ASC', 'limit' => 2];

    $this->controller->list($this->get(['page' => '2']), new Response());

    $listed = $this->service->lastList;
    $this->assertInstanceOf(ListState::class, $listed);
    $this->assertSame(2, $listed->limit);
    $this->assertSame(2, $listed->offset);
    $this->assertSame('yoga', $listed->search);
    $this->assertSame('ordering ASC', $listed->orderBy);

    // countAll never sees the window.
    $this->assertSame(0, $this->service->lastCount->offset);
  }

  public function testAnExtraFilterOpensTheFilterbar(): void
  {
    $this->controller->filters = ['idCat' => ['0' => 'Toutes', '4' => 'Sculptures']];
    $this->controller->state = ['search' => '', 'orderBy' => 'ordering ASC', 'limit' => 25, 'idCat' => 4];

    $this->controller->list($this->get(), new Response());

    $this->assertTrue($this->controller->lastVData['showFilterbar']);
    $this->assertTrue($this->controller->lastVData['filterbar']['filterActive']);
    $this->assertSame(4, $this->service->lastList->filter('idCat'));
  }

  public function testTheAllValueOfAnExtraFilterLeavesTheBarClosed(): void
  {
    $this->controller->filters = ['idCat' => ['0' => 'Toutes', '4' => 'Sculptures']];
    $this->controller->state = ['search' => '', 'orderBy' => 'ordering ASC', 'limit' => 25, 'idCat' => 0];

    $this->controller->list($this->get(), new Response());

    $this->assertFalse($this->controller->lastVData['filterbar']['filterActive']);
    $this->assertSame(0, $this->service->lastList->filter('idCat'));
  }

  public function testPaginationAppearsPastOnePage(): void
  {
    $this->controller->state = ['search' => '', 'orderBy' => 'ordering ASC', 'limit' => 2];

    $this->controller->list($this->get(), new Response());

    $this->assertIsArray($this->controller->lastVData['pagination']);
  }

  public function testTheOrderingSortOverrideDrivesOrderValid(): void
  {
    $this->controller->ordering = 'ordering DESC';
    $this->controller->state = ['search' => '', 'orderBy' => 'ordering DESC', 'limit' => 25];

    $this->controller->list($this->get(), new Response());

    $this->assertTrue($this->controller->lastVData['ordervalid']);
    $this->assertSame('DESC', $this->controller->lastVData['orderDir']);
    $this->assertTrue($this->controller->rows[0]['orderValid']);
  }

  public function testAnotherSortDisablesDragReorder(): void
  {
    $this->controller->state = ['search' => '', 'orderBy' => 'created DESC', 'limit' => 25];

    $this->controller->list($this->get(), new Response());

    $this->assertFalse($this->controller->lastVData['ordervalid']);
    $this->assertFalse($this->controller->rows[0]['orderValid']);
  }

  // ------------------------------------------------------------ add/edit --

  public function testAddRendersABlankForm(): void
  {
    $this->controller->add($this->get(), new Response());

    $this->assertSame('views/admin/item.tmpl', $this->controller->lastView);
    $this->assertSame('form', $this->controller->lastTask);
    $this->assertSame('Nouveau', $this->controller->lastVData['toolbar']['subtitle']);
  }

  public function testEditRendersTheItem(): void
  {
    $this->controller->edit($this->get(['id' => '2']), new Response());

    $this->assertSame('views/admin/item.tmpl', $this->controller->lastView);
    $this->assertSame('Modifier', $this->controller->lastVData['toolbar']['subtitle']);
  }

  public function testAnUnknownEditBouncesToTheListWithAFlash(): void
  {
    $response = $this->controller->edit($this->get(['id' => '99']), new Response());

    $this->assertSame(302, $response->getStatusCode());
    $this->assertSame('/admin/things/list/', $response->getHeaderLine('Location'));
    $this->assertSame([['Élément introuvable.', 'error']], $this->controller->flashes);
  }

  // ---------------------------------------------------------------- save --

  public function testSaveRedirectsToTheList(): void
  {
    $response = $this->controller->save($this->post(['title' => 'Neuf']), new Response());

    $this->assertSame(302, $response->getStatusCode());
    $this->assertSame('/admin/things/list/', $response->getHeaderLine('Location'));
    $this->assertSame([['Élément créé.', 'success']], $this->controller->flashes);
    $this->assertSame('Neuf', $this->service->rows[4]->title);
  }

  public function testApplyStaysOnTheEditedItem(): void
  {
    $response = $this->controller->apply($this->post(['id' => '2', 'title' => 'Revu']), new Response());

    $this->assertSame(302, $response->getStatusCode());
    $this->assertSame('/admin/things/edit/?id=2', $response->getHeaderLine('Location'));
    $this->assertSame([['Élément enregistré.', 'success']], $this->controller->flashes);
  }

  public function testAFailedSaveIs422WithThePostedValues(): void
  {
    $response = $this->controller->save($this->post(['id' => '2', 'title' => '']), new Response());

    $this->assertSame(422, $response->getStatusCode());
    $this->assertSame('', $response->getHeaderLine('Location'));
    $this->assertSame([['Titre requis.', 'error']], $this->controller->flashes);
    // The form was rebuilt over the posted body, not over the stored row.
    $this->assertSame(['id' => '2', 'title' => ''], $this->controller->lastFormInput);
  }

  public function testCancelGoesBackToTheList(): void
  {
    $response = $this->controller->cancel($this->get(), new Response());

    $this->assertSame(302, $response->getStatusCode());
    $this->assertSame('/admin/things/list/', $response->getHeaderLine('Location'));
  }

  public function testTheBaseUrlIsOverridable(): void
  {
    $relocated = new RelocatedAdminController($this->service);

    $response = $relocated->cancel($this->get(), new Response());

    $this->assertSame(302, $response->getStatusCode());
    $this->assertSame('/bureau/things/list/', $response->getHeaderLine('Location'));
    // and the whole screen follows, not just the redirect
    $relocated->list($this->get(), new Response());
    $this->assertSame(
      '/bureau/things/add/',
      $relocated->lastVData['toolbar']['buttons'][0]['attrs']['href'],
    );
  }

  public function testTheViewNamesAreOverridable(): void
  {
    $retemplated = new RetemplatedAdminController($this->service);

    $retemplated->list($this->get(), new Response());
    $this->assertSame('views/items.twig', $retemplated->lastView);

    $retemplated->add($this->get(), new Response());
    $this->assertSame('views/item.twig', $retemplated->lastView);

    $retemplated->edit($this->get(['id' => '2']), new Response());
    $this->assertSame('views/item.twig', $retemplated->lastView);
  }

  // ----------------------------------------------------------- selection --

  public function testPublishTakesTheCsvSelection(): void
  {
    $response = $this->controller->publish($this->get(['id' => '1,3']), new Response());

    $this->assertSame(302, $response->getStatusCode());
    $this->assertSame([[1, 3], 1], $this->service->lastPublished);
    $this->assertSame([['Sélection publiée.', 'success']], $this->controller->flashes);
  }

  public function testUnpublishTakesTheCsvSelection(): void
  {
    $this->controller->unpublish($this->get(['id' => '2']), new Response());

    $this->assertSame([[2], 0], $this->service->lastPublished);
    $this->assertSame([['Sélection dépubliée.', 'success']], $this->controller->flashes);
  }

  public function testAnEmptySelectionTouchesNothing(): void
  {
    $this->controller->publish($this->get(), new Response());

    $this->assertNull($this->service->lastPublished);
    $this->assertSame([], $this->controller->flashes);
  }

  public function testTrashSingularAndPlural(): void
  {
    $this->controller->trash($this->get(['id' => '1']), new Response());
    $this->assertSame([1], $this->service->lastDeleted);
    $this->assertSame([['Élément supprimé.', 'success']], $this->controller->flashes);

    $this->controller->flashes = [];
    $this->controller->trash($this->get(['id' => '2,3']), new Response());
    $this->assertSame([2, 3], $this->service->lastDeleted);
    $this->assertSame([['Éléments supprimés.', 'success']], $this->controller->flashes);
  }

  // --------------------------------------------------------------- json --

  public function testJsonPublishRepaintsTheRow(): void
  {
    $data = $this->json($this->controller->jsonPublish($this->post(['id' => '2']), new Response()));

    $this->assertTrue($data['success']);
    $this->assertSame(1, $data['published']);
    $this->assertSame('Élément publié.', $data['message']);
    $this->assertSame([[2], 1], $this->service->lastPublished);
  }

  public function testJsonUnpublishRepaintsTheRow(): void
  {
    $data = $this->json($this->controller->jsonUnpublish($this->post(['id' => '2,3']), new Response()));

    $this->assertSame(0, $data['published']);
    $this->assertSame('Élément dépublié.', $data['message']);
    $this->assertSame([[2, 3], 0], $this->service->lastPublished);
  }

  public function testJsonPublishWithoutAnIdIsAnError(): void
  {
    $data = $this->json($this->controller->jsonPublish($this->post([]), new Response()));

    $this->assertSame('Élément introuvable.', $data['error']);
    $this->assertNull($this->service->lastPublished);
  }

  public function testChangeorderAnswersTheSuccessAdminJsWaitsFor(): void
  {
    $get = $this->json($this->controller->changeorder($this->get(['id' => '2', 'newValue' => '1']), new Response()));
    $this->assertSame(['success' => true], $get);
    $this->assertSame([2, 1], $this->service->lastMoved);

    $post = $this->json($this->controller->changeorder($this->post(['id' => '3', 'newValue' => '2']), new Response()));
    $this->assertSame(['success' => true], $post);
    $this->assertSame([3, 2], $this->service->lastMoved);
  }

  public function testChangeorderIgnoresAnIncompleteMove(): void
  {
    $this->controller->changeorder($this->get(['id' => '2']), new Response());

    $this->assertNull($this->service->lastMoved);
  }

  public function testSetAndResetFilters(): void
  {
    $set = $this->json($this->controller->setFilters($this->post(['filter' => ['search' => 'yoga']]), new Response()));
    $this->assertSame(['success' => true], $set);
    $this->assertSame(['things', ['search' => 'yoga']], $this->controller->filtersSet);

    $reset = $this->json($this->controller->resetFilters($this->post([]), new Response()));
    $this->assertSame(['success' => true], $reset);
    $this->assertSame('things', $this->controller->filtersReset);
  }

  // ------------------------------------------------------------ contract --

  public function testListStateSplitsTheKnownKeysFromTheExtraFilters(): void
  {
    $state = ListState::fromFilters(['search' => 'a', 'orderBy' => 'id DESC', 'limit' => 10, 'idCat' => '7'], 20);

    $this->assertSame(10, $state->limit);
    $this->assertSame(20, $state->offset);
    $this->assertSame('a', $state->search);
    $this->assertSame('id DESC', $state->orderBy);
    $this->assertSame(['idCat' => '7'], $state->filters);
    $this->assertSame(7, $state->filter('idCat'));
    $this->assertSame('7', $state->filter('idCat', ''));
    $this->assertSame(0, $state->filter('nope'));
    $this->assertSame('all', $state->filter('nope', 'all'));
  }

  public function testSaveResult(): void
  {
    $ok = SaveResult::ok(4);
    $this->assertTrue($ok->isOk());
    $this->assertSame(4, $ok->id);
    $this->assertSame([], $ok->errors);

    $ko = SaveResult::fail(['title' => 'Titre requis.'], 2);
    $this->assertFalse($ko->isOk());
    $this->assertSame(2, $ko->id);
    $this->assertSame(['title' => 'Titre requis.'], $ko->errors);
  }

  // ------------------------------------------------------------- helpers --

  private function get(array $query = []): ServerRequestInterface
  {
    return (new ServerRequestFactory())->createServerRequest('GET', '/admin/things/list/')
      ->withQueryParams($query);
  }

  private function post(array $body): ServerRequestInterface
  {
    return (new ServerRequestFactory())->createServerRequest('POST', '/admin/things/save/')
      ->withParsedBody($body);
  }

  private function json(ResponseInterface $response): array
  {
    $response->getBody()->rewind();

    return \json_decode((string) $response->getBody(), true);
  }
}

/**
 * Three rows, no database — enough to answer everything the trait asks.
 */
final class FakeAdminService implements AdminServiceInterface
{
  /** @var array<int,object> */
  public array $rows = [];
  public ?ListState $lastCount = null;
  public ?ListState $lastList = null;
  public ?array $lastPublished = null;
  public ?array $lastDeleted = null;
  public ?array $lastMoved = null;

  public function __construct()
  {
    foreach ([[1, 'Un'], [2, 'Deux'], [3, 'Trois']] as [$id, $title]) {
      $this->rows[$id] = (object) ['id' => $id, 'title' => $title, 'ordering' => $id, 'published' => 1];
    }
  }

  public function count(ListState $state): int
  {
    $this->lastCount = $state;

    return \count($this->rows);
  }

  public function list(ListState $state): array
  {
    $this->lastList = $state;
    $rows = \array_values($this->rows);

    return $state->limit > 0 ? \array_slice($rows, $state->offset, $state->limit) : $rows;
  }

  public function find(int $id): ?object
  {
    return $this->rows[$id] ?? null;
  }

  public function save(array $input, ?int $id): SaveResult
  {
    if ($id && !isset($this->rows[$id])) {
      return SaveResult::fail(['_' => 'Introuvable.']);
    }

    $title = \trim((string) ($input['title'] ?? ''));
    if ('' === $title) {
      return SaveResult::fail(['title' => 'Titre requis.'], $id);
    }

    if ($id) {
      $this->rows[$id]->title = $title;

      return SaveResult::ok($id);
    }

    $id = \max(\array_keys($this->rows)) + 1;
    $this->rows[$id] = (object) ['id' => $id, 'title' => $title, 'ordering' => $id, 'published' => 1];

    return SaveResult::ok($id);
  }

  public function setPublished(array $ids, int $published): void
  {
    $this->lastPublished = [$ids, $published];
  }

  public function delete(array $ids): void
  {
    $this->lastDeleted = $ids;
  }

  public function moveOrdering(int $id, int $newValue): void
  {
    $this->lastMoved = [$id, $newValue];
  }
}

final class TestForm extends Form
{
  public function configure(): static
  {
    return $this;
  }

  protected function populate(): void
  {
    $this->makeFormFieldset('main');

    $id = $this->makeFormRow('id', 'main');
    $id->setField(new HiddenField('id'));

    $title = $this->makeFormRow('title', 'main');
    $title->setField(new Text('title'));
  }
}

/**
 * The site wiring base, reduced to what the trait needs — everything it
 * would normally reach through AdminRenderer / Session / Filters is a
 * recording stub here.
 */
class TestAdminController
{
  use AdminControllerTrait;

  public array $state = ['search' => '', 'orderBy' => 'ordering ASC', 'limit' => 25];
  public array $filters = [];
  public string $ordering = 'ordering ASC';

  public string $lastView = '';
  public array $lastVData = [];
  public string $lastComponent = '';
  public string $lastTask = '';
  public array $lastFormInput = [];
  public array $rows = [];
  public array $flashes = [];
  public ?array $filtersSet = null;
  public ?string $filtersReset = null;

  public function __construct(private AdminServiceInterface $adminService) {}

  protected function component(): string
  {
    return 'things';
  }

  protected function title(): string
  {
    return 'Choses';
  }

  protected function service(): AdminServiceInterface
  {
    return $this->adminService;
  }

  protected function buildRow(object $item, bool $orderValid): Row
  {
    $this->rows[] = ['id' => (int) $item->id, 'orderValid' => $orderValid];

    return new Row([
      'id' => (int) $item->id,
      'label' => (string) $item->title,
      'ordering' => (int) $item->ordering,
      'published' => (int) $item->published,
    ]);
  }

  protected function buildForm(?object $item, array $input = []): \JDZ\Form\Form
  {
    $this->lastFormInput = $input;

    $form = new TestForm('adminForm');
    $form->init(new \JDZ\Form\FormData([
      'id' => (string) ($item->id ?? ''),
      'title' => (string) ($input['title'] ?? $item->title ?? ''),
    ]));

    return $form;
  }

  protected function orderingSort(): string
  {
    return $this->ordering;
  }

  protected function filterOptions(): array
  {
    return $this->filters;
  }

  protected function adminRender(ResponseInterface $response, string $view, array $vData, string $component, string $task): ResponseInterface
  {
    $this->lastView = $view;
    $this->lastVData = $vData;
    $this->lastComponent = $component;
    $this->lastTask = $task;

    return $response;
  }

  protected function adminFlash(string $message, string $type = 'success'): void
  {
    $this->flashes[] = [$message, $type];
  }

  protected function adminCsrfToken(): string
  {
    return 'csrf-token';
  }

  protected function adminFilterState(string $component): array
  {
    return $this->state;
  }

  protected function adminFiltersSet(string $component, array $filter): void
  {
    $this->filtersSet = [$component, $filter];
  }

  protected function adminFiltersReset(string $component): void
  {
    $this->filtersReset = $component;
  }
}

/**
 * A site that serves its back office somewhere else than the package default:
 * one override, and every task URL follows.
 */
final class RelocatedAdminController extends TestAdminController
{
  protected function adminBaseUrl(): string
  {
    return '/bureau';
  }
}

/**
 * A site that names its admin screens its own way: one override, and both the
 * list and the form follow. This is what a `templates/admin/` site does.
 */
final class RetemplatedAdminController extends TestAdminController
{
  protected function adminViewName(string $name): string
  {
    return 'views/' . $name . '.twig';
  }
}
