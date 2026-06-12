<?php

namespace Drupal\eca_render\Plugin\Action;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Core\TypedData\ListInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\DataType\DataTransferObject;

/**
 * Attach a drupalSettings value to an existing render array element.
 */
#[Action(
  id: 'eca_render_add_attached_setting',
  label: new TranslatableMarkup('Render: add attached drupalSettings'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Add a drupalSettings entry to an existing render array element. Only works when reacting upon a rendering event, such as <em>Build form</em> or <em>Build ECA Block</em>.'),
  version_introduced: '3.0.12',
)]
class AddAttachedSetting extends AddAttachedBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'collection' => '',
      'key' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['collection'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Collection name'),
      '#description' => $this->t('drupalSettings module name or collection name.'),
      '#default_value' => $this->configuration['collection'],
      '#weight' => -35,
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    $form['key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Key'),
      '#description' => $this->t('drupalSettings key within the collection.'),
      '#default_value' => $this->configuration['key'],
      '#weight' => -30,
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['collection'] = $form_state->getValue('collection');
    $this->configuration['key'] = $form_state->getValue('key');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function buildAttachedArray(array &$attachments): void {
    $collection = trim((string) $this->tokenService->replaceClear($this->configuration['collection']));
    $key = trim((string) $this->tokenService->replaceClear($this->configuration['key']));
    $value = $this->configuration['value'];

    $use_token_replace = TRUE;
    // Check whether the input wants to directly use defined data.
    if ((mb_substr($value, 0, 1) === '[') && (mb_substr($value, -1, 1) === ']') && (mb_strlen($value) <= 255) && ($data = $this->tokenService->getTokenData($value))) {
      if (!($data instanceof TypedDataInterface) || !empty($data->getValue())) {
        $use_token_replace = FALSE;
        $value = static::stringifyRecursive($data);
      }
    }
    if ($use_token_replace) {
      $value = trim((string) $this->tokenService->replaceClear($value));
    }

    if (!empty($collection) && !empty($key)) {
      $attachments['drupalSettings'][$collection][$key] = $value;
    }
  }

  /**
   * Traverse arrays or objects and cast all children to primitive types.
   *
   * @param mixed $value
   *   The input object, array, or string.
   *
   * @return array|string
   *   A xss-filtered string if $value was scalar.
   *   An array of mixed depth if $value was traversable.
   */
  protected static function stringifyRecursive(mixed $value): array|string {
    if (is_object($value)) {
      if ($value instanceof DataTransferObject || $value instanceof ComplexDataInterface) {
        $value = $value->toArray();
      }
      elseif ($value instanceof ListInterface) {
        $value = [...$value];
      }
      elseif ($value instanceof TypedDataInterface) {
        $value = $value->getString();
      }
      else {
        $value = (string) $value;
      }
    }

    if (is_string($value)) {
      $value = Xss::filterAdmin(trim($value));
    }
    elseif (is_array($value)) {
      $value = array_map([static::class, 'stringifyRecursive'], $value);
    }

    return $value;
  }

}
