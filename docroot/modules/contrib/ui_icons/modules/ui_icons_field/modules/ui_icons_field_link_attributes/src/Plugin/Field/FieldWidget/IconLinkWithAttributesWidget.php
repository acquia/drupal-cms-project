<?php

declare(strict_types=1);

namespace Drupal\ui_icons_field_link_attributes\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface;
use Drupal\link_attributes\LinkAttributesManager;
use Drupal\link_attributes\LinkWithAttributesWidgetTrait;
use Drupal\ui_icons_field\IconFieldTrait;
use Drupal\ui_icons_field\IconLinkWidgetTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;

/**
 * Plugin implementation of the 'link field with attributes' widget.
 */
#[FieldWidget(
  id: 'icon_link_attributes_widget',
  label: new TranslatableMarkup('Link icon (with attributes)'),
  field_types: ['link'],
)]
class IconLinkWithAttributesWidget extends LinkWidget implements ContainerFactoryPluginInterface {

  use IconFieldTrait;

  use IconLinkWidgetTrait {
    IconLinkWidgetTrait::defaultSettings as protected traitIconDefaultSettings;
    IconLinkWidgetTrait::settingsForm as protected traitIconSettingsForm;
    IconLinkWidgetTrait::settingsSummary as protected traitIconSettingsSummary;
    IconLinkWidgetTrait::formElement as protected traitIconFormElement;
  }

  use LinkWithAttributesWidgetTrait {
    LinkWithAttributesWidgetTrait::defaultSettings as protected traitDefaultSettings;
    LinkWithAttributesWidgetTrait::settingsForm as protected traitSettingsForm;
    LinkWithAttributesWidgetTrait::settingsSummary as protected traitSettingsSummary;
    LinkWithAttributesWidgetTrait::formElement as protected traitFormElement;
  }

  /**
   * The icon pack manager.
   *
   * @var \Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface
   */
  protected IconPackManagerInterface $pluginManagerIconPack;

  /**
   * Link attributes plugin manager.
   *
   * @var \Drupal\link_attributes\LinkAttributesManager
   */
  protected LinkAttributesManager $linkAttributesManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->pluginManagerIconPack = $container->get('plugin.manager.icon_pack');
    $instance->linkAttributesManager = $container->get('plugin.manager.link_attributes');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    // Parent:: is called by traitDefaultSettings().
    $settings = self::traitDefaultSettings();
    $settings += self::traitIconDefaultSettings();
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    // Parent:: is called by traitSettingsForm().
    $elements = $this->traitSettingsForm($form, $form_state);
    $elements += $this->traitIconSettingsForm($form, $form_state);
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    // Parent:: is called by traitSettingsSummary().
    $summary = array_merge(
      $this->traitSettingsSummary(),
      $this->traitIconSettingsSummary()
    );
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    // Parent:: is called by traitFormElement().
    $element = $this->traitFormElement($items, $delta, $element, $form, $form_state);
    $element = $this->traitIconFormElement($items, $delta, $element, $form, $form_state);
    return $element;
  }

}
