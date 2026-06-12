<?php

declare(strict_types=1);

namespace Drupal\drupical\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\drupical\EventsRenderer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an 'Events Feed' block.
 */
#[Block(
  id: 'events_block',
  admin_label: new TranslatableMarkup('Events Feed'),
)]
class EventsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new EventsBlock instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\drupical\EventsRenderer $eventsRenderer
   *   The EventsRenderer service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EventsRenderer $eventsRenderer,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('drupical.renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'label' => 'Drupal Events and User Groups',
      'label_display' => 'visible',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockAccess(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, 'access events');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return $this->eventsRenderer->render(5, 0);
  }

}
