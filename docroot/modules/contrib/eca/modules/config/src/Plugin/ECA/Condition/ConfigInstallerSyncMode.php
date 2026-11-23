<?php

namespace Drupal\eca_config\Plugin\ECA\Condition;

use Drupal\Core\Config\ConfigInstallerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaCondition;
use Drupal\eca\Plugin\ECA\Condition\ConditionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * ECA condition plugin to determine config installer sync mode.
 */
#[EcaCondition(
  id: 'eca_config_installer_sync_mode',
  label: new TranslatableMarkup('Config installer sync mode'),
  description: new TranslatableMarkup('Determine if config installer is in sync mode.'),
  version_introduced: '2.1.3',
)]
class ConfigInstallerSyncMode extends ConditionBase {

  /**
   * The config installer.
   *
   * @var \Drupal\Core\Config\ConfigInstallerInterface
   */
  protected ConfigInstallerInterface $configInstaller;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->configInstaller = $container->get('config.installer');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    $result = $this->configInstaller->isSyncing();
    return $this->negationCheck($result);
  }

}
