<?php

namespace Drupal\eca_content\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\eca\Attribute\EcaAction;

/**
 * Set the value of an entity field.
 */
#[Action(
  id: 'eca_set_field_value',
  label: new TranslatableMarkup('Entity: set field value'),
  type: 'entity',
)]
#[EcaAction(
  description: new TranslatableMarkup('Allows to set, unset or change the value(s) of any field in an entity.'),
  version_introduced: '1.0.0',
)]
class SetFieldValue extends FieldUpdateActionBase implements EcaFieldUpdateActionInterface {

  /**
   * {@inheritdoc}
   *
   * This is an ECA-only action and must not be exposed externally.
   */
  public static function externallyAvailable(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function getFieldsToUpdate() {
    $name = $this->tokenService->replace($this->configuration['field_name']);

    // Process the field values.
    $values = $this->configuration['field_value'];
    $use_token_replace = TRUE;
    // Check whether the input wants to directly use defined data.
    if ((mb_substr($values, 0, 1) === '[') && (mb_substr($values, -1, 1) === ']') && (mb_strlen($values) <= 255) && ($data = $this->tokenService->getTokenData($values))) {
      if (!($data instanceof TypedDataInterface) || !empty($data->getValue())) {
        $use_token_replace = FALSE;
        $values = $data;
      }
    }
    if ($use_token_replace) {
      $values = $this->tokenService->replaceClear($values);
    }

    return [$name => $values];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'field_name' => '',
      'field_value' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['field_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Field name'),
      '#description' => $this->t('The machine name of the field, that should be changed. Example: <em>body.value</em>'),
      '#default_value' => $this->configuration['field_name'],
      '#weight' => -50,
      '#eca_token_replacement' => TRUE,
    ];
    $form['field_value'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Field value'),
      '#description' => $this->t('The new field value.'),
      '#default_value' => $this->configuration['field_value'],
      '#weight' => -45,
      '#eca_token_replacement' => TRUE,
      // The "clear" method (Empty the field value) always empties the field and
      // ignores any value entered here, so this field is hidden via #states
      // keyed on the method select when that method is selected - matching the
      // convention used by other ECA action forms (e.g. the HTMX response
      // header action).
      '#states' => [
        'invisible' => [
          ':input[name="method"]' => ['value' => 'clear'],
        ],
      ],
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['field_name'] = $form_state->getValue('field_name');
    $this->configuration['field_value'] = $form_state->getValue('field_value');
    parent::submitConfigurationForm($form, $form_state);
  }

}
