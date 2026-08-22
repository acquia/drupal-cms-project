<?php

declare(strict_types=1);

namespace Drupal\eca_render\Event;

use Drupal\breakpoint\Breakpoint;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * An event dispatched when ECA is altering breakpoint information.
 */
final class EcaRenderBreakpointsAlterEvent extends Event {

  public function __construct(
    private array &$definitions,
  ) {}

  /**
   * Creates or overrides a breakpoint plugin definition.
   *
   * @param string $id
   *   A breakpoint plugin ID (e.g., `custom.x_large`).
   * @param array $definition
   *   A complete or partial breakpoint definition. If a breakpoint with the
   *   given ID is already defined, these values will be merged into it.
   */
  public function mergeDefinition(string $id, array $definition): void {
    $definition += ($this->definitions[$id] ?? []) + [
      'class' => Breakpoint::class,
      'provider' => 'eca_render',
      'group' => 'eca_render',
    ];
    $this->definitions[$id] = $definition;
  }

}
