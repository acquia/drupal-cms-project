<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper\Plugin\ConfigAction;

use Drupal\Core\Config\Action\Attribute\ConfigAction;
use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\system\Form\DevelopmentSettingsForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Enables or disables theme development mode.
 *
 * This programmatically submits the development settings form to toggle
 * Twig debug, Twig cache, and rendered output cache bins.
 *
 * An example of using this in a recipe:
 *
 * @code
 * system.theme:
 *   themeDevelopmentMode: true
 * @endcode
 *
 * @api
 *   This is part of Drupal CMS's developer-facing API and may be relied upon.
 */
#[ConfigAction(
  id: 'themeDevelopmentMode',
  admin_label: new TranslatableMarkup('Toggle theme development mode'),
)]
final readonly class ThemeDevelopmentMode implements ConfigActionPluginInterface, ContainerFactoryPluginInterface {

  public function __construct(
    private FormBuilderInterface $formBuilder,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $container->get(FormBuilderInterface::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function apply(string $configName, mixed $value): void {
    assert($configName === 'system.theme', 'Theme development mode can only be toggled by the system.theme config object.');
    assert(is_bool($value));

    // An unchecked checkbox's value is NULL.
    // @see \Drupal\Core\Render\Element\Checkbox::valueCallback()
    $value = $value ?: NULL;
    $form_state = (new FormState())
      ->setValue('disable_rendered_output_cache_bins', $value)
      ->setValue('twig_development_mode', $value)
      ->setValue('twig_debug', $value)
      ->setValue('twig_cache_disable', $value);
    $this->formBuilder->submitForm(DevelopmentSettingsForm::class, $form_state);
  }

}
