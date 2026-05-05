<?php

namespace Drupal\ai_provider_amazeeio\AmazeeIoApi;

/**
 * Interface for Amazee API Client.
 */
interface ClientInterface {

  /**
   * Set the auth token to use for future requests.
   *
   * @param string $token
   *   The token.
   */
  public function setToken(string $token): void;

  /**
   * Set the auth token to use for future requests.
   *
   * @param string $host
   *   The host domain.
   */
  public function setHost(string $host): void;

  /**
   * Get the team ID for the current user.
   *
   * @return int
   *   The team ID.
   */
  public function getTeamId(): int;

  /**
   * Attempt to log in to the Amazee API.
   *
   * @param string $username
   *   The username to log in with.
   * @param string $password
   *   The password to log in with.
   *
   * @return string
   *   The access token or an empty string on failure.
   */
  public function login(string $username, string $password): string;

  /**
   * Attempt to log out to the Amazee API.
   *
   * @return bool
   *   Whether the operation was successful.
   */
  public function logout(): bool;

  /**
   * Request a validation code for a given email address.
   */
  public function requestCode(string $email): void;

  /**
   * Validate an email validation code.
   *
   * @return ?string
   *   The access token for this account or null if the code was invalid.
   */
  public function validateCode(string $email, string $code): ?string;

  /**
   * Attempt to register and log in to the Amazee API.
   *
   * @param string $email
   *   The email address to register.
   * @param string $password
   *   The password to register.
   *
   * @return string
   *   The access token or an empty string on failure.
   */
  public function register(string $email, string $password): string;

  /**
   * Whether the client has authorized access.
   *
   * @return bool
   *   Whether the client has authorized access.
   */
  public function authorized(): bool;

  /**
   * Get a list of available regions from the API.
   *
   * @return array
   *   Array of region names, keyed by ID.
   */
  public function getRegions(): array;

  /**
   * Created a Private AI key from the API.
   *
   * @param string $region_id
   *   The region for the key.
   * @param string $name
   *   The name for the key.
   * @param int|null $team_id
   *   (optional) The team ID to associate the key with.
   *
   * @return array<string, string>
   *   Info about the created Private AI key. Keys are:
   *     - litellm_token: the token to use.
   *     - litellm_api_url: the API URL to use.
   */
  public function createPrivateAiKey(string $region_id, string $name, ?int $team_id = NULL): array;

  /**
   * Get the private keys for the authorized user from the API.
   *
   * @return array<\stdClass>
   *   An array of the available keys.
   */
  public function getPrivateApiKeys(): array;

  /**
   * Get info about a specific API key.
   *
   * @param string $api_key
   *   The API key to get info for.
   *
   * @return \stdClass|null
   *   The API key object or NULL if it doesn't exist.
   */
  public function getPrivateApiKey(string $api_key): ?\stdClass;

}
