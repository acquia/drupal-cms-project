<?php

namespace Drupal\ai_dashboard\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\system\SystemManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block for "Admin Menu Links".
 *
 * @Block(
 *   id = "admin_menu_links",
 *   admin_label = @Translation("Admin Menu Links"),
 *   category = @Translation("AI Dashboard"),
 * )
 */
#[Block(
  id: "admin_menu_links",
  admin_label: new TranslatableMarkup("Admin Menu Links"),
  category: new TranslatableMarkup("AI Dashboard"),
)]
class AdminLinks extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The system manager service.
   *
   * @var \Drupal\system\SystemManager
   */
  protected SystemManager $systemManager;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, SystemManager $system_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->systemManager = $system_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('system.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    return $this->systemManager->getBlockContents();
  }

}
