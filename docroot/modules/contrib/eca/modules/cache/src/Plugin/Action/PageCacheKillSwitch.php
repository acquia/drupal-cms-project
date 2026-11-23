<?php

namespace Drupal\eca_cache\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;

/**
 * Provides a Page Cache Kill Switch action.
 */
#[Action(
  id: 'eca_page_cache_kill_switch',
  label: new TranslatableMarkup('Page Cache Kill Switch'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Kill the page cache for this page'),
  version_introduced: '2.0.0',
)]
class PageCacheKillSwitch extends ConfigurableActionBase {

  /**
   * The kill switch response policy.
   *
   * @var \Drupal\Core\PageCache\ResponsePolicy\KillSwitch
   */
  protected KillSwitch $killSwitch;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $self = parent::create(
      $container,
      $configuration,
      $plugin_id,
      $plugin_definition
    );
    $self->killSwitch = $container->get('page_cache_kill_switch');
    return $self;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    $this->killSwitch->trigger();
  }

}
