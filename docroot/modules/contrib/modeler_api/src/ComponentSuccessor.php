<?php

namespace Drupal\modeler_api;

/**
 * Contains a component successor.
 */
readonly class ComponentSuccessor {

  /**
   * Instantiates a new component successor.
   */
  public function __construct(
    protected string $id,
    protected string $conditionId,
  ) {}

  /**
   * Get the ID.
   *
   * @return string
   *   The ID.
   */
  public function getId(): string {
    return $this->id;
  }

  /**
   * Get the ID of the condition.
   *
   * @return string
   *   The ID of the condition.
   */
  public function getConditionId(): string {
    return $this->conditionId;
  }

}
