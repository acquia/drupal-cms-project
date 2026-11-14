<?php

declare(strict_types=1);

namespace Drupal\ui_icons_field\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\Icon\IconDefinition;
use Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ui_icons_field\IconFieldHelpers;

/**
 * Plugin implementation of the 'icon_formatter' formatter.
 */
#[FieldFormatter(
  id: 'icon_formatter',
  label: new TranslatableMarkup('Icon'),
  field_types: [
    'ui_icon',
  ],
)]
class IconFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The icon pack manager.
   *
   * @var \Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface
   */
  protected IconPackManagerInterface $pluginManagerIconPack;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition,): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->pluginManagerIconPack = $container->get('plugin.manager.icon_pack');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'icon_settings' => [],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = [];

    if ($this->getSetting('icon_settings')) {
      $summary[] = $this->t('Specific icon settings saved');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);

    $this->pluginManagerIconPack->getExtractorPluginForms(
      $elements,
      $form_state,
      $this->getSetting('icon_settings') ?: [],
      // @todo views do not retrieve FieldType value saved.
      $this->fieldDefinition->getSetting('allowed_icon_pack') ?: [],
      TRUE
    );

    // Placeholder to get all settings serialized as the form keys are dynamic
    // and based on icon pack definition options.
    // @todo change to #element_submit when available.
    // @see https://drupal.org/i/2820359
    $elements['icon_settings'] = [
      '#type' => 'hidden',
      '#element_validate' => [
        [$this, 'validateSettings'],
      ],
    ];

    return $elements;
  }

  /**
   * Validation callback for extractor settings element.
   *
   * @param array $element
   *   The element being processed.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   */
  public function validateSettings(array $element, FormStateInterface $form_state, &$complete_form): void {
    $filtered_values = IconFieldHelpers::validateSettings($element, $form_state->getValues());

    // Set the value for the element in the form state to be saved.
    $form_state->setValueForElement($element, $filtered_values);
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];

    foreach ($items as $delta => $item) {
      if ($item->isEmpty()) {
        continue;
      }

      $icon_full_id = $item->get('target_id')->getValue();
      $formatter_settings = $this->getSetting('icon_settings') ?? [];
      if (!is_array($formatter_settings)) {
        $formatter_settings = [];
      }

      $elements[$delta] = IconDefinition::getRenderable($icon_full_id, $formatter_settings);
    }

    return $elements;
  }

}
