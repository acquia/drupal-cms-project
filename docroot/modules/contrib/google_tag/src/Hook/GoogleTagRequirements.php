<?php

declare(strict_types=1);

namespace Drupal\google_tag\Hook;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Requirements hook implementations for google_tag.
 *
 * Drupal 11.3 and later call updateRequirements() as a hook. Earlier versions
 * call google_tag_requirements(), which uses this class too.
 */
final class GoogleTagRequirements implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * Constructs a GoogleTagRequirements object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ModuleHandlerInterface $moduleHandler,
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('config.factory'),
    );
  }

  /**
   * Implements hook_update_requirements().
   *
   * Blocks the update when both Google Tag 1.x containers and Google
   * Analytics settings exist, because the upgrade path has to be chosen.
   */
  #[Hook('update_requirements')]
  public function updateRequirements(): array {
    if (!$this->moduleHandler->moduleExists('google_analytics')) {
      return [];
    }
    $entities = $this->entityTypeManager->getStorage('google_tag_container')->loadMultiple();
    $ga_settings = $this->configFactory->get('google_analytics.settings');
    if (!$entities || $ga_settings->get('account') === '' || $ga_settings->get('custom.parameters') === []) {
      return [];
    }
    // @todo provide a drush command usage example here.
    // Where users can choose between google_tag 1.x
    // and google_analytics for migration.
    return [
      'google_tag' => [
        'title' => $this->t('Google Tag'),
        'description' => $this->t('In order to use Google Tag 2.x, you must decide the upgrade path between Google Tag 1.x and Google Analytics.'),
        'severity' => self::errorSeverity(),
        'value' => 'Google Tag 2.x is incompatible with Google Analytics while upgrading from 1.x.',
      ],
    ];
  }

  /**
   * Returns the error severity for the running core version.
   *
   * @return \Drupal\Core\Extension\Requirement\RequirementSeverity|int
   *   RequirementSeverity::Error on Drupal 11.2 and later, REQUIREMENT_ERROR
   *   before that. REQUIREMENT_ERROR is defined in core/includes/install.inc,
   *   which update.php loads before it calls the requirements hooks.
   */
  public static function errorSeverity(): RequirementSeverity|int {
    return DeprecationHelper::backwardsCompatibleCall(
      \Drupal::VERSION,
      '11.2.0',
      fn() => RequirementSeverity::Error,
      fn() => \REQUIREMENT_ERROR,
    );
  }

}
