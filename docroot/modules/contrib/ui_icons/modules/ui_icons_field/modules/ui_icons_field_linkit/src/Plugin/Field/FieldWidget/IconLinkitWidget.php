<?php

declare(strict_types=1);

namespace Drupal\ui_icons_field_linkit\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface;
use Drupal\ui_icons_field\IconFieldTrait;
use Drupal\ui_icons_field\IconLinkWidgetTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\linkit\Plugin\Field\FieldWidget\LinkitWidget;

/**
 * Plugin implementation of the 'linkit field with icon' widget.
 */
#[FieldWidget(
  id: 'icon_linkit_widget',
  label: new TranslatableMarkup('Linkit icon'),
  field_types: ['link'],
)]
class IconLinkitWidget extends LinkitWidget implements ContainerFactoryPluginInterface {

  use IconFieldTrait, IconLinkWidgetTrait {
    IconLinkWidgetTrait::defaultSettings as protected traitDefaultSettings;
    IconLinkWidgetTrait::settingsForm as protected traitSettingsForm;
    IconLinkWidgetTrait::settingsSummary as protected traitSettingsSummary;
    IconLinkWidgetTrait::formElement as protected traitFormElement;
  }

  /**
   * The icon pack manager.
   *
   * @var \Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface
   */
  protected IconPackManagerInterface $pluginManagerIconPack;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->pluginManagerIconPack = $container->get('plugin.manager.icon_pack');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    $settings = parent::defaultSettings();
    $settings += self::traitDefaultSettings();
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);
    $elements += $this->traitSettingsForm($form, $form_state);
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = array_merge(
      parent::settingsSummary(),
      $this->traitSettingsSummary()
    );
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $element = $this->traitFormElement($items, $delta, $element, $form, $form_state);
    return $element;
  }

}
