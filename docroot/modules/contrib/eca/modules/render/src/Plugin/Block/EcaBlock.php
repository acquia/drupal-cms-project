<?php

namespace Drupal\eca_render\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Event\RenderEventInterface;
use Drupal\eca\Event\TriggerEvent;
use Drupal\eca_render\RenderEvents;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The ECA Block plugin.
 */
#[Block(
  id: 'eca',
  admin_label: new TranslatableMarkup('ECA Block'),
  category: new TranslatableMarkup('ECA'),
  deriver: EcaBlockDeriver::class
)]
final class EcaBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The build of the render array.
   *
   * @var array
   */
  public array $build = [];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The service for triggering ECA-related events.
   *
   * @var \Drupal\eca\Event\TriggerEvent
   */
  protected TriggerEvent $triggerEvent;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): EcaBlock {
    return new EcaBlock(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('eca.trigger_event'),
      $container->get('state'),
    );
  }

  /**
   * The EcaBlock constructor.
   *
   * @param array $configuration
   *   The settings configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\eca\Event\TriggerEvent $trigger_event
   *   The service for triggering ECA-related events.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, TriggerEvent $trigger_event, StateInterface $state) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->triggerEvent = $trigger_event;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $event = $this->triggerEvent->dispatchFromPlugin('eca_render:block', $this);
    if ($event instanceof RenderEventInterface) {
      $this->build = &$event->getRenderArray();
    }
    return ['content' => $this->build];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    $dependencies = parent::calculateDependencies();
    foreach ($this->getEcaConfigurations() as $eca) {
      $dependencies[$eca->getConfigDependencyKey()][] = $eca->getConfigDependencyName();
    }
    return $dependencies;
  }

  /**
   * Get the ECA configurations, that define this block.
   *
   * @return \Drupal\eca\Entity\Eca[]
   *   The ECA configurations, keyed by config entity ID.
   */
  public function getEcaConfigurations(): array {
    $block_event_name = $this->getDerivativeId();
    $configs = [];
    $subscribed = current($this->state->get('eca.subscribed', [])[RenderEvents::BLOCK] ?? []);
    if (!$subscribed) {
      return $configs;
    }
    foreach (array_keys($subscribed) as $eca_id) {
      /** @var \Drupal\eca\Entity\Eca|null $eca */
      $eca = $this->entityTypeManager->getStorage('eca')->load($eca_id);
      if ($eca === NULL || !$eca->status()) {
        continue;
      }
      foreach (($eca->get('events') ?? []) as $event) {
        if ($event['plugin'] !== 'eca_render:block') {
          continue;
        }
        $configured_event_machine_name = $event['configuration']['block_machine_name'] ?? '';
        if ($configured_event_machine_name === $block_event_name) {
          $configs[$eca->id()] = $eca;
        }
      }
    }
    return $configs;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    // Add some sensible defaults for cache contexts.
    return array_unique(array_merge([
      'url.path',
      'url.query_args',
      'user',
      'user.permissions',
    ], parent::getCacheContexts()));
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    // Add ECA config as cache tag for automatic invalidation.
    return array_unique(array_merge(['config:eca_list'], parent::getCacheTags()));
  }

}
