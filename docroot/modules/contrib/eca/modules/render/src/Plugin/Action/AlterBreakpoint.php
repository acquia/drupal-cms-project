<?php

declare(strict_types=1);

namespace Drupal\eca_render\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Service\YamlParser;
use Drupal\eca_render\Event\EcaRenderBreakpointsAlterEvent;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Defines an action that can alter breakpoint plugin definitions.
 */
#[Action(
  id: 'eca_render_alter_breakpoint',
  label: new TranslatableMarkup('Render: alter breakpoint'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Allows creating or altering breakpoint definitions, which are normally defined by theme or module code.'),
  version_introduced: '3.0.4',
)]
class AlterBreakpoint extends ConfigurableActionBase {

  /**
   * The YAML parser.
   *
   * @var \Drupal\eca\Service\YamlParser
   */
  protected YamlParser $yamlParser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->yamlParser = $container->get('eca.service.yaml_parser');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = AccessResult::allowedIf($this->event instanceof EcaRenderBreakpointsAlterEvent);
    if ($result->isAllowed()) {
      try {
        $this->yamlParser->parse($this->configuration['definition']);
      }
      catch (ParseException) {
        $result = AccessResult::forbidden('YAML data is not valid.');
      }
    }
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    assert($this->event instanceof EcaRenderBreakpointsAlterEvent);
    $this->event->mergeDefinition($this->configuration['id'], $this->yamlParser->parse($this->configuration['definition']));
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'id' => '',
      'definition' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('ID'),
      '#default_value' => $this->configuration['id'],
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    $form['definition'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Definition'),
      '#default_value' => $this->configuration['definition'],
      '#description' => $this->t('These values will override values from an existing breakpoint definition with the same ID.'),
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['id'] = $form_state->getValue('id');
    $this->configuration['definition'] = $form_state->getValue('definition');
    parent::submitConfigurationForm($form, $form_state);
  }

}
