<?php

namespace Drupal\ai_provider_amazeeio\Hook;

use Drupal\search_api\Entity\Server;
use Drupal\key\Plugin\KeyProvider\ConfigKeyProvider;
use Drupal\key\Entity\Key;
use Drupal\config_ignore\ConfigIgnoreConfig;
use Drupal\user\UserInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\ai_search\Plugin\search_api\backend\SearchApiAiSearchBackend;
use Drupal\search_api\IndexInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for ai_provider_amazeeio.
 */
class AiProviderAmazeeioHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public static function theme(): array {
    return [
      'amazeeio_ai_dashboard' => [
        'render element' => 'element',
      ],
    ];
  }

  /**
   * Implements hook_search_api_index_update().
   *
   * When the search API index is updated, check the fields are correctly
   * setup in Postgres.
   */
  #[Hook('search_api_index_update')]
  public static function searchApiIndexUpdate(IndexInterface $index): void {
    $server = $index->getServerInstance();
    if (!$server || !$server->getBackend() instanceof SearchApiAiSearchBackend) {
      return;
    }
    $backend_config = $server->getBackendConfig();
    if (!isset($backend_config['database'])) {
      return;
    }
    if ($backend_config['database'] !== 'amazeeio_vector_db') {
      return;
    }
    $ai_vdb_provider_postgres = \Drupal::service('ai.vdb_provider')->createInstance('amazeeio_vector_db');
    $connection = $ai_vdb_provider_postgres->getConnection($backend_config['database_settings']['database_name']);
    \Drupal::service('ai_provider_amazeeio.postgres_client')->updateFields($index->getFields(), $backend_config['database_settings']['collection'], $connection);
  }

  /**
   * Implements hook_user_logout().
   */
  #[Hook('user_logout')]
  public static function userLogout(AccountInterface $account): void {
    // Log the user out of amazee.ai during Drupal logout.
    _ai_provider_amazeeio_amazee_logout();
  }

  /**
   * Implements hook_user_login().
   */
  #[Hook('user_login')]
  public static function userLogin(UserInterface $account): void {
    // Log the user out of amazee.ai during log in in case the last Drupal
    // logout was due to timeout/inactivity.
    _ai_provider_amazeeio_amazee_logout();
    // Trigger (flag) the redirect to the settings page, if needed.
    $configFactory = \Drupal::configFactory();
    // Only run this if config
    // ai_provider_amazeeio.settings.redirect_on_login is TRUE.
    $config = $configFactory->get('ai_provider_amazeeio.settings');
    if ($config->get('redirect_on_login')) {
      _ai_provider_amazeeio_redirect_to_settings_page();
    }
  }

  /**
   * Implements hook_config_ignore_ignored_alter().
   */
  #[Hook('config_ignore_ignored_alter')]
  public static function configIgnoreIgnoredAlter(ConfigIgnoreConfig $config): void {
    $config_list = [
          // Environment-specific information.
      'ai_provider_amazeeio.settings:host',
      'ai_provider_amazeeio.settings:postgres_default_database',
      'ai_provider_amazeeio.settings:postgres_host',
          // Credentials.
      'ai_provider_amazeeio.settings:postgres_username',
    ];
    // More credentials.
    $settings = \Drupal::config('ai_provider_amazeeio.settings');
    $api_key_id = $settings->get('api_key');
    if (!empty($api_key_id) && is_string($api_key_id)) {
      $ai_key = Key::load($api_key_id);
      if ($ai_key && $ai_key->getKeyProvider() instanceof ConfigKeyProvider) {
        $config_list[] = 'key.key.amazeeio_ai:key_provider_settings.key_value';
      }
    }
    $db_password_id = $settings->get('postgres_password');
    if (!empty($db_password_id) && is_string($db_password_id)) {
      $vdb_key = Key::load($db_password_id);
      if ($vdb_key && $vdb_key->getKeyProvider() instanceof ConfigKeyProvider) {
        $config_list[] = 'key.key.amazeeio_ai_database:key_provider_settings.key_value';
      }
    }
    if (\Drupal::moduleHandler()->moduleExists('ai_search')) {
      $ai_search_backends = \Drupal::entityQuery('search_api_server')->condition('backend', 'search_api_ai_search')->execute();
      if ($ai_search_backends !== []) {
        foreach (Server::loadMultiple($ai_search_backends) as $server) {
          $database_backend = $server->getBackendConfig()['database'] ?? '';
          if ($database_backend === 'amazeeio_vector_db') {
            $config_list[] = sprintf('search_api.server.%s:backend_config.database_settings.database_name', $server->id());
          }
        }
      }
    }
    $config->mergeWith(new ConfigIgnoreConfig('simple', $config_list));
  }

}
