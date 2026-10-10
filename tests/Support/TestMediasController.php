<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\AdminUi\Tests\Support;

use JDZ\AdminUi\Controller\Traits\MediasControllerTrait;
use JDZ\MediaManager\ValueObject\MediaConfig;
use Psr\Http\Message\ResponseInterface;

/**
 * The smallest consumer the trait admits : media config, a translator that
 * echoes keys back, an in-memory state bag and stub renderers. Every hook the
 * trait calls is recorded, so a test can assert what it was handed.
 */
class TestMediasController
{
  use MediasControllerTrait;

  /** The session bag mediaStateLoad() / mediaStateSave() read and write. */
  public array $state = [];

  /** Every view name the trait asked for, in order. */
  public array $views = [];

  /** Every fragment rendered, as [view, vData]. */
  public array $fragments = [];

  /** The full page rendered by display(), as [view, vData]. */
  public ?array $page = null;

  /** Every translation asked for, as [key, params]. */
  public array $translations = [];

  /**
   * @param array<string,mixed> $config MediaConfig::fromArray() keys overriding the defaults
   */
  public function __construct(private string $root, private array $config = []) {}

  protected function mediaConfig(): MediaConfig
  {
    return MediaConfig::fromArray($this->config + [
      'rootPath' => $this->root,
      'systemFolders' => ['systeme'],
      'extsImage' => ['gif', 'jpg'],
      'mimesImage' => ['image/gif', 'image/jpeg'],
      'maxWeightImage' => 2,
      'maxNumFiles' => 0,
      'maxWeightFiles' => 0,
    ]);
  }

  protected function mediaTranslate(string $key, array $params = []): string
  {
    $this->translations[] = [$key, $params];

    return $key;
  }

  /** Renders just enough of each view to assert the contract on. */
  protected function mediaRenderFragment(string $view, array $vData): string
  {
    $this->views[] = $view;
    $this->fragments[] = [$view, $vData];

    if (\str_contains($view, 'selector')) {
      $html = '<div data-mode="' . $vData['mode'] . '">';

      foreach ($vData['folders'] as $one) {
        $html .= '<option value="' . $one['value'] . '">' . $one['name'] . '</option>';
      }

      if (null !== $vData['current']) {
        $html .= '<fieldset class="m-editor"'
          . ' data-src="' . $vData['current']['src'] . '"'
          . ' data-alt="' . $vData['current']['alt'] . '"'
          . ' data-caption="' . $vData['current']['caption'] . '"'
          . ' data-align="' . $vData['current']['align'] . '"></fieldset>';
      }

      foreach ($vData['tree'] as $folder) {
        foreach ($folder->files as $file) {
          $html .= '<a data-m-value="' . $file->value . '" data-m-url="' . $file->url
            . '" data-m-thumb="' . $file->thumb . '" data-m-orientation="' . $file->orientation . '"></a>';
        }
      }

      return $html . '</div>';
    }

    if (\str_contains($view, 'finfos')) {
      return \implode('', $vData['file']['infos']);
    }

    return '<form action="' . ($vData['action'] ?? '') . '">'
      . '<input name="m[newName]" value="' . ($vData['fileName'] ?? $vData['oldName'] ?? '') . '" />'
      . '<input name="_csrf" value="' . $vData['csrf'] . '" />'
      . '</form>';
  }

  protected function mediaRenderPage(ResponseInterface $response, string $view, array $vData): ResponseInterface
  {
    $this->page = [$view, $vData];

    $response->getBody()->write($view);

    return $response;
  }

  protected function mediaCsrfToken(): string
  {
    return 'csrf-token';
  }

  protected function mediaStateLoad(): array
  {
    return $this->state;
  }

  protected function mediaStateSave(array $state): void
  {
    $this->state = $state;
  }

  protected function mediaThumbUrl(string $relPath, int $width): string
  {
    return 'thumb:' . $relPath . '@' . $width;
  }

  protected function mediaSelectorImage(string $relPath, int $width): ?array
  {
    return ['thumb' => 'thumb:' . $relPath . '@' . $width, 'orientation' => 'square'];
  }
}
