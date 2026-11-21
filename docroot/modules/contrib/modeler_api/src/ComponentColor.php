<?php

namespace Drupal\modeler_api;

/**
 * Contains a component colors.
 */
readonly class ComponentColor {

  /**
   * Instantiates a new component successor.
   */
  public function __construct(
    protected string $fill,
    protected string $stroke,
  ) {}

  /**
   * Get the fill color.
   *
   * @return string
   *   The fill color.
   */
  public function getFill(): string {
    return $this->fill;
  }

  /**
   * Get the stroke color.
   *
   * @return string
   *   The stroke color.
   */
  public function getStroke(): string {
    return $this->stroke;
  }

}
