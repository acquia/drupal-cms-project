<?php

namespace Drupal\eca_render\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\ECA\PluginFormTrait;
use Drupal\eca_render\Event\EcaRenderAlterLinkEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Alter a link element by setting its language.
 */
#[Action(
  id: 'eca_render_alter_link_set_language',
  label: new TranslatableMarkup('Render: alter link, set language'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Alter a link element by setting its language.'),
  version_introduced: '3.0.3',
)]
class AlterLinkSetLanguage extends AlterLinkBase {

  use PluginFormTrait;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManager
   */
  protected LanguageManager $languageManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->languageManager = $container->get('language_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    if (!$this->event instanceof EcaRenderAlterLinkEvent) {
      return;
    }
    $langcode = trim($this->configuration['langcode']);
    if ($langcode === '_interface') {
      $langcode = $this->languageManager->getCurrentLanguage()->getId();
    }
    elseif ($langcode === '_eca_token') {
      $langcode = $this->getTokenValue('langcode', $this->languageManager->getCurrentLanguage()->getId());
    }
    if ($langcode === '') {
      $this->event->setLanguage(NULL);
    }
    else {
      $language = $this->languageManager->getLanguage($langcode);
      if ($language === NULL) {
        throw new \InvalidArgumentException(sprintf("No language found for langcode %s.", $langcode));
      }
      $this->event->setLanguage($language);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'langcode' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $langcodes = array_map(function ($language) {
      return $language->getName();
    }, $this->languageManager->getLanguages());
    $langcodes['_interface'] = $this->t('Interface language');
    $form['langcode'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $langcodes,
      '#default_value' => $this->configuration['langcode'],
      '#description' => $this->t('The language code to be set. Select "undefined" to unset the language.'),
      '#eca_token_select_option' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['langcode'] = $form_state->getValue('langcode');
    parent::submitConfigurationForm($form, $form_state);
  }

}
