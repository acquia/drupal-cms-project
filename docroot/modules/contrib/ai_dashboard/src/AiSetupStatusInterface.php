<?php

namespace Drupal\ai_dashboard;

/**
 * Interface for AiSetupStatus service.
 */
interface AiSetupStatusInterface {

  /**
   * Gets the provider setup status.
   *
   * Return value is not boolean, as sometimes it is not possible to determine
   * the status in case the `getSetupData` doesn't return the configuration
   * property name for the key machine name, or the configuration object that
   * stores this value is not in the "main" configuration settings file of the
   * provider module, normally it should be `ai_provider_<name>.settings`.
   *
   * @param string $provider_name
   *   The AI provider name.
   *
   * @return string
   *   Enum: not-available, yes, no.
   */
  public function getProviderSetupStatus($provider_name);

}
