<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\AdminUi\ValueObject;

/**
 * ModalChrome represents the chrome configuration for modal dialogs.
 * Replaces the scattered response->data->set() calls for title, noheader, closeIcon, size.
 * 
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class ModalChrome
{
  public const SIZE_SM = 'sm';
  public const SIZE_MD = 'md';
  public const SIZE_LG = 'lg';
  public const SIZE_XL = 'xl';

  public function __construct(
    public string $title = '',
    public string $size = self::SIZE_MD,
    public bool $noheader = false,
    public bool $closeIcon = true
  ) {}

  /**
   * Create a ModalChrome from an array of data
   */
  public static function fromArray(array $data): static
  {
    return new static(
      $data['title'] ?? '',
      $data['size'] ?? self::SIZE_MD,
      $data['noheader'] ?? false,
      $data['closeIcon'] ?? true
    );
  }

  /**
   * Convert to array for response data
   */
  public function toArray(): array
  {
    return [
      'title' => $this->title,
      'size' => $this->size,
      'noheader' => $this->noheader,
      'closeIcon' => $this->closeIcon,
    ];
  }

  /**
   * Set the title
   */
  public function withTitle(string $title): static
  {
    $new = clone $this;
    $new->title = $title;
    return $new;
  }

  /**
   * Set the size
   */
  public function withSize(string $size): static
  {
    $new = clone $this;
    $new->size = $size;
    return $new;
  }

  /**
   * Set noheader flag
   */
  public function withNoHeader(bool $noheader = true): static
  {
    $new = clone $this;
    $new->noheader = $noheader;
    return $new;
  }

  /**
   * Set closeIcon flag
   */
  public function withCloseIcon(bool $closeIcon = true): static
  {
    $new = clone $this;
    $new->closeIcon = $closeIcon;
    return $new;
  }
}