<?php

namespace Drupal\ai_agents\Service\AgentStatus\Interfaces\UpdateItems;

/**
 * Defines an interface for when the request is sent.
 */
interface AiProviderRequestInterface extends StatusBaseInterface {

  /**
   * Get the loop number of the iteration.
   *
   * @return int
   *   The loop number.
   */
  public function getLoopNumber(): int;

  /**
   * Set the loop number of the iteration.
   *
   * @param int $loop_number
   *   The loop number.
   */
  public function setLoopNumber(int $loop_number): void;

  /**
   * Get the name of the AI provider.
   *
   * @return string
   *   The provider name.
   */
  public function getProviderName(): string;

  /**
   * Set the name of the AI provider.
   *
   * @param string $provider_name
   *   The provider name.
   */
  public function setProviderName(string $provider_name): void;

  /**
   * Get the name of the model being used.
   *
   * @return string
   *   The model name.
   */
  public function getModelName(): string;

  /**
   * Set the name of the model being used.
   *
   * @param string $model_name
   *   The model name.
   */
  public function setModelName(string $model_name): void;

  /**
   * Get the configuration array for the provider request.
   *
   * @return array
   *   The configuration array.
   */
  public function getModelConfig(): array;

  /**
   * Set the configuration array for the provider request.
   *
   * @param array $config
   *   The configuration array.
   */
  public function setModelConfig(array $config): void;

  /**
   * Get the request data being sent to the provider.
   *
   * @return array
   *   The request data.
   */
  public function getRequestData(): array;

  /**
   * Set the request data being sent to the provider.
   *
   * @param array $request_data
   *   The request data.
   */
  public function setRequestData(array $request_data): void;

}
