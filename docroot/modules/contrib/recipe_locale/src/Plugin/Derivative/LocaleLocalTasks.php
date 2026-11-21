<?php

declare(strict_types=1);

namespace Drupal\recipe_locale\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the tabs on the translation status report.
 *
 * Core's report has no tabs of its own. This adds one for the report itself
 * and one for the recipe list next to it. Without the Interface Translation
 * module there is no report and no recipe list, so there are no tabs either.
 */
final class LocaleLocalTasks extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id): static {
    return new static($container->get('module_handler'));
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    if (!$this->moduleHandler->moduleExists('locale')) {
      return [];
    }
    $this->derivatives['translate_status'] = [
      'route_name' => 'locale.translate_status',
      'base_route' => 'locale.translate_status',
      'title' => $this->t('Available updates for all projects'),
    ] + $base_plugin_definition;
    $this->derivatives['recipes'] = [
      'route_name' => 'recipe_locale.recipes',
      'base_route' => 'locale.translate_status',
      'title' => $this->t('Recipe translation updates'),
      'weight' => 10,
    ] + $base_plugin_definition;
    return $this->derivatives;
  }

}
