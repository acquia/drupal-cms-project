<?php

namespace Drupal\eca_render\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Event\RenderEventInterface;
use Drupal\eca\Plugin\FormFieldMachineName;

/**
 * Base class for actions related to adding render element attachments.
 */
abstract class AddAttachedBase extends RenderActionBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'name' => '',
      'value' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['name'] = [
      '#type' => 'textfield',
      '#maxlength' => 1024,
      '#element_validate' => [[FormFieldMachineName::class, 'validateElementsMachineName']],
      '#title' => $this->t('Machine name'),
      '#description' => $this->t('Specify the machine name / key of the render element.<br>Leave blank to target parent.'),
      '#default_value' => $this->configuration['name'],
      '#weight' => -50,
      '#eca_token_replacement' => TRUE,
    ];
    $form['value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Value'),
      '#description' => $this->t('Value to attach.'),
      '#default_value' => $this->configuration['value'],
      '#weight' => -25,
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['name'] = $form_state->getValue('name');
    $this->configuration['value'] = $form_state->getValue('value');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    $event = $this->event;
    if (!($event instanceof RenderEventInterface)) {
      return;
    }

    $name = trim((string) $this->tokenService->replaceClear($this->configuration['name']));
    $build = &$event->getRenderArray();
    if (empty($name)) {
      $element = &$build;
    }
    else {
      $element = &$this->getTargetElement($name, $build);
    }

    if ($element) {
      $element['#attached'] ??= [];
      $this->buildAttachedArray($element['#attached']);
    }
  }

  /**
   * Inner logic for building the attachments array.
   *
   * @param array &$attachments
   *   The original attachments array.
   *   Passed from `$element['#attached']`.
   */
  abstract protected function buildAttachedArray(array &$attachments): void;

}
