<?php

namespace Drupal\eca_content\Plugin\Action;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TypedData\ListInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\eca\Plugin\Action\ActionBase;
use Drupal\eca\Plugin\Action\ConfigurableActionTrait;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\eca\Plugin\ECA\PluginFormTrait;
use Drupal\eca\Processor;
use Drupal\eca\TypedData\PropertyPathTrait;
use Drupal\eca_content\Plugin\EntitySaveTrait;

/**
 * Replaces Drupal\Core\Field\FieldUpdateActionBase.
 *
 * <p>We need to replace the core base class because within the ECA context
 * entities should not be saved after modifying a field value.</p>
 *
 * <p>The replacement is achieved with PHP's class_alias(),
 * see eca_content.module.</p>
 */
abstract class FieldUpdateActionBase extends ActionBase implements ConfigurableInterface, DependentPluginInterface, PluginFormInterface {

  use ConfigurableActionTrait;
  use EntitySaveTrait;
  use PluginFormTrait;
  use PropertyPathTrait;

  /**
   * {@inheritdoc}
   *
   * This base class replaces core's FieldUpdateActionBase via class_alias().
   * Core actions (e.g. PromoteNode) that extend this base must remain visible
   * outside ECA, so this returns TRUE. ECA-specific subclasses like
   * SetFieldValue override this to return FALSE.
   */
  public static function externallyAvailable(): bool {
    return TRUE;
  }

  /**
   * Gets an array of values to be set.
   *
   * @return array
   *   Array of values with field names as keys.
   */
  abstract protected function getFieldsToUpdate();

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    if (!($this instanceof SetFieldValue)) {
      return parent::defaultConfiguration();
    }
    return [
      'method' => 'set:clear',
      'strip_tags' => FALSE,
      'trim' => FALSE,
      'save_entity' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    if (!($this instanceof SetFieldValue)) {
      return $form;
    }
    // The "clear" method (Empty the field value) ignores any value entered, so
    // value-shaping options are hidden via #states when that method is
    // selected.
    $hidden_for_clear = [
      'invisible' => [
        ':input[name="method"]' => ['value' => 'clear'],
      ],
    ];
    $form['method'] = [
      '#type' => 'select',
      '#title' => $this->t('Method'),
      '#default_value' => $this->configuration['method'],
      '#description' => $this->t('The method to set an entity, like cleaning the old one, etc..'),
      '#weight' => -40,
      '#options' => [
        'set:clear' => $this->t('Set and clear previous value'),
        'set:force_clear' => $this->t('Set and enforce clear previous value'),
        'set:empty' => $this->t('Set only when empty'),
        'append:not_full' => $this->t('Append when not full yet'),
        'append:drop_first' => $this->t('Append and drop first when full'),
        'append:drop_last' => $this->t('Append and drop last when full'),
        'prepend:not_full' => $this->t('Prepend when not full yet'),
        'prepend:drop_first' => $this->t('Prepend and drop first when full'),
        'prepend:drop_last' => $this->t('Prepend and drop last when full'),
        'remove' => $this->t('Remove value instead of adding it'),
        'clear' => $this->t('Empty the field value'),
      ],
      '#eca_token_select_option' => TRUE,
    ];
    $form['strip_tags'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Strip tags'),
      '#default_value' => $this->configuration['strip_tags'],
      '#description' => $this->t('Remove the tags or not.'),
      '#weight' => -30,
      // Stripping tags only shapes a value being set, which is meaningless
      // when the "clear" method discards any entered value.
      '#states' => $hidden_for_clear,
    ];
    $form['trim'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Trim'),
      '#default_value' => $this->configuration['trim'],
      '#description' => $this->t('Trims the field value or not.'),
      '#weight' => -20,
      // Trimming only shapes a value being set, which is meaningless when the
      // "clear" method discards any entered value.
      '#states' => $hidden_for_clear,
    ];
    $form['save_entity'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Save entity'),
      '#default_value' => $this->configuration['save_entity'],
      '#description' => $this->t('Saves the entity or not after setting the value.'),
      '#weight' => -10,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    if (!($this instanceof SetFieldValue)) {
      return;
    }

    $this->configuration['method'] = $form_state->getValue('method');
    $this->configuration['strip_tags'] = !empty($form_state->getValue('strip_tags'));
    $this->configuration['trim'] = !empty($form_state->getValue('trim'));
    $this->configuration['save_entity'] = !empty($form_state->getValue('save_entity'));
  }

  /**
   * The save method.
   *
   * <p>Helper function to save the entity only outside ECA context or when
   * requested explicitly.</p>
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity which might have to be saved.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function save(FieldableEntityInterface $entity): void {
    if (!empty($this->configuration['save_entity']) || !Processor::get()->isEcaContext()) {
      $this->saveEntity($entity);
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  public function execute(mixed $entity = NULL): void {
    if (!($entity instanceof FieldableEntityInterface)) {
      return;
    }
    $method = $this->configuration['method'] ?? ($this->defaultConfiguration()['method'] ?? 'set:clear');
    if ($method === '_eca_token') {
      $method = $this->getTokenValue('method', 'set:clear');
    }

    $method_settings = explode(':', $method);
    $all_entities_to_save = [];
    $options = ['auto_append' => TRUE, 'access' => 'update'];
    $values_changed = FALSE;
    foreach ($this->getFieldsToUpdate() as $field => $values) {
      $metadata = [];
      if (!($update_target = $this->getTypedProperty($entity->getTypedData(), $field, $options, $metadata))) {
        throw new \InvalidArgumentException(sprintf("The provided field %s does not exist as a property path on the %s entity having ID %s.", $field, $entity->getEntityTypeId(), $entity->id()));
      }
      if (empty($metadata['entities'])) {
        throw new \RuntimeException(sprintf("The provided field %s does not resolve for entities to be saved from the %s entity having ID %s.", $field, $entity->getEntityTypeId(), $entity->id()));
      }
      $property_name = $update_target->getName();
      $delta = 0;
      while ($update_target = $update_target->getParent()) {
        if (is_int($update_target->getName())) {
          $delta = $update_target->getName();
        }
        if ($update_target instanceof FieldItemListInterface) {
          break;
        }
      }
      $is_property_name_explicit = in_array($property_name, $metadata['parts'], TRUE);
      $is_delta_explicit = in_array((string) $delta, $metadata['parts'], TRUE);
      if (!($update_target instanceof FieldItemListInterface)) {
        throw new \InvalidArgumentException(sprintf("The provided field %s does not resolve to a field on the %s entity having ID %s.", $field, $entity->getEntityTypeId(), $entity->id()));
      }
      if ($values instanceof ListInterface) {
        $values = $values->getValue();
      }
      elseif ($values instanceof DataTransferObject) {
        if ($properties = $values->getProperties()) {
          $values = [];
          foreach ($properties as $k => $v) {
            $values[$k] = $v instanceof DataTransferObject ? $v->toArray() : $v->getValue();
          }
        }
        else {
          $values = [$delta => $values->getString()];
        }
      }
      elseif (!is_array($values)) {
        $values = [$delta => $values];
      }
      if (!isset($values[$delta])) {
        $values[$delta] = end($values);
        unset($values[key($values)]);
      }

      $values = $this->normalizeFieldValues($values, $property_name, $is_property_name_explicit);

      // Custom filtering of field values is applied here, because some fields
      // do actually want to have an incomplete intermediary state of a field
      // value, that would be then completed by a subsequent action. Therefore
      // a manual filter is performed here.
      /**
       * @var \Drupal\Core\Field\FieldItemListInterface $update_target
       */
      $current_values = array_filter($update_target->getValue(), function ($value) {
        if (is_array($value)) {
          foreach ($value as $v) {
            if (!is_null($v)) {
              return TRUE;
            }
          }
          return FALSE;
        }
        return !is_null($value) && ($value !== '');
      });

      if ($is_delta_explicit) {
        /** @var array $values */
        $values += $current_values;
        ksort($values);
      }

      // The dedicated "clear" method always empties the field, while the
      // "set:clear" method only empties it when no replacement value was
      // provided and the field is not already empty (kept for backward
      // compatibility). Field access is enforced separately in access().
      $is_clear_method = ($method === 'clear') ||
        (empty($values) && !empty($current_values) && ($method === 'set:clear'));
      if ($is_clear_method) {
        // Shorthand for setting a field to be empty.
        if ($is_property_name_explicit) {
          $update_target->get($delta)->$property_name = NULL;
        }
        else {
          $update_target->setValue([]);
        }
        foreach ($metadata['entities'] as $entity_to_save) {
          if (!in_array($entity_to_save, $all_entities_to_save, TRUE)) {
            $all_entities_to_save[] = $entity_to_save;
          }
        }
        continue;
      }

      // Create a map of indices that refer to the already existing counterpart.
      $existing = $this->identifyExistingIndices($current_values, $values, $property_name, $method_settings);

      if ((reset($method_settings) !== 'remove') && (count($existing) === count($values)) && (count($existing) === count($current_values))) {
        continue;
      }

      $cardinality = $update_target->getFieldDefinition()->getFieldStorageDefinition()->getCardinality();
      $is_unlimited = $cardinality === FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED;
      foreach ($method_settings as $method_setting) {
        switch ($method_setting) {

          case 'force_clear':
            $existing = $current_values = [];
            break;

          case 'clear':
            $this->executeClear($existing, $current_values, $values_changed);
            break;

          case 'empty':
            if (empty($current_values)) {
              break;
            }
            if ($is_delta_explicit && empty($current_values[$delta])) {
              break;
            }
            if ($is_property_name_explicit && empty($current_values[$delta][$property_name])) {
              break;
            }
            continue 3;

          case 'not_full':
            if (!$is_unlimited && !(count($current_values) < $cardinality)) {
              continue 3;
            }
            break;

          case 'drop_first':
            $this->executeDropFirst($is_unlimited, $values, $existing, $cardinality, $current_values, $values_changed);
            break;

          case 'drop_last':
            $this->executeDropLast($is_unlimited, $values, $existing, $cardinality, $current_values, $values_changed);
            break;

        }
      }

      foreach ($method_settings as $method_setting) {
        switch ($method_setting) {

          case 'set':
            $this->executeSet($current_values, $values, $is_delta_explicit, $is_property_name_explicit, $delta, $existing, $is_unlimited, $cardinality, $values_changed);
            break;

          case 'append':
            $this->executeAppend($current_values, $values, $is_unlimited, $cardinality, $existing, $values_changed);
            break;

          case 'prepend':
            $this->executePrepend($current_values, $values, $is_unlimited, $cardinality, $existing, $values_changed);
            break;

          case 'remove':
            $this->executeRemove($existing, $current_values, $values_changed);
            break;

        }
      }

      if ($values_changed) {
        // Try to set the values. If that attempt fails, then it would throw an
        // exception, and the exception would be logged as an error.
        $update_target->setValue(array_values($current_values));
        foreach ($metadata['entities'] as $entity_to_save) {
          if (!in_array($entity_to_save, $all_entities_to_save, TRUE)) {
            $all_entities_to_save[] = $entity_to_save;
          }
        }
      }
    }
    foreach ($all_entities_to_save as $to_save) {
      $this->save($to_save);
    }
  }

  /**
   * Handles the "clear" operation.
   *
   * @param array $existing
   *   The map of existing indices.
   * @param array $current_values
   *   The current field values.
   * @param bool $values_changed
   *   Whether the values have changed.
   */
  private function executeClear(array $existing, array &$current_values, bool &$values_changed): void {
    $keep = [];
    foreach ($existing as $k) {
      $keep[$k] = $current_values[$k];
    }
    if (count($current_values) !== count($keep)) {
      $values_changed = TRUE;
    }
    $current_values = $keep;
  }

  /**
   * Handles the "drop_first" removal operation.
   *
   * @param bool $is_unlimited
   *   Whether the field has unlimited cardinality.
   * @param array $values
   *   The new values.
   * @param array $existing
   *   The map of existing indices.
   * @param int $cardinality
   *   The field cardinality.
   * @param array $current_values
   *   The current field values.
   * @param bool $values_changed
   *   Whether the values have changed.
   */
  private function executeDropFirst(bool $is_unlimited, array $values, array $existing, int $cardinality, array &$current_values, bool &$values_changed): void {
    if (!$is_unlimited) {
      $num_required = count($values) - count($existing) - ($cardinality - count($current_values));
      $keep = array_flip($existing);
      reset($current_values);
      while ($num_required > 0 && ($k = key($current_values)) !== NULL) {
        next($current_values);
        $num_required--;
        if (!isset($keep[$k])) {
          unset($current_values[$k]);
          $values_changed = TRUE;
        }
      }
    }
  }

  /**
   * Handles the "drop_last" removal operation.
   *
   * @param bool $is_unlimited
   *   Whether the field has unlimited cardinality.
   * @param array $values
   *   The new values.
   * @param array $existing
   *   The map of existing indices.
   * @param int $cardinality
   *   The field cardinality.
   * @param array $current_values
   *   The current field values.
   * @param bool $values_changed
   *   Whether the values have changed.
   */
  private function executeDropLast(bool $is_unlimited, array $values, array $existing, int $cardinality, array &$current_values, bool &$values_changed): void {
    if (!$is_unlimited) {
      $num_required = count($values) - count($existing) - ($cardinality - count($current_values));
      $keep = array_flip($existing);
      end($current_values);
      while ($num_required > 0 && ($k = key($current_values)) !== NULL) {
        prev($current_values);
        $num_required--;
        if (!isset($keep[$k])) {
          unset($current_values[$k]);
          $values_changed = TRUE;
        }
      }
    }
  }

  /**
   * Handles the "set" operation.
   *
   * @param array $current_values
   *   The current field values.
   * @param array $values
   *   The new values.
   * @param bool $is_delta_explicit
   *   Whether the delta is explicit.
   * @param bool $is_property_name_explicit
   *   Whether the property name is explicit.
   * @param int $delta
   *   The delta.
   * @param array $existing
   *   The map of existing indices.
   * @param bool $is_unlimited
   *   Whether the field has unlimited cardinality.
   * @param int $cardinality
   *   The field cardinality.
   * @param bool $values_changed
   *   Whether the values have changed.
   */
  private function executeSet(array &$current_values, array $values, bool $is_delta_explicit, bool $is_property_name_explicit, int $delta, array $existing, bool $is_unlimited, int $cardinality, bool &$values_changed): void {
    $current_num = count($current_values);
    foreach ($values as $i => $value) {
      if (($is_delta_explicit || ($is_property_name_explicit && ($delta === 0) && ($i === 0))) && !isset($existing[$i])) {
        $current_values[$i] = $value;
        $values_changed = TRUE;
        continue;
      }
      if (!$is_unlimited && $cardinality <= $current_num) {
        break;
      }
      if (!isset($existing[$i])) {
        $current_num++;
        $current_values[] = $value;
        $values_changed = TRUE;
      }
    }
    ksort($current_values);
  }

  /**
   * Handles the "append" operation.
   *
   * @param array $current_values
   *   The current field values.
   * @param array $values
   *   The new values.
   * @param bool $is_unlimited
   *   Whether the field has unlimited cardinality.
   * @param int $cardinality
   *   The field cardinality.
   * @param array $existing
   *   The map of existing indices.
   * @param bool $values_changed
   *   Whether the values have changed.
   */
  private function executeAppend(array &$current_values, array $values, bool $is_unlimited, int $cardinality, array $existing, bool &$values_changed): void {
    $current_num = count($current_values);
    foreach ($values as $i => $value) {
      if (!$is_unlimited && $cardinality <= $current_num) {
        break;
      }
      if (!isset($existing[$i])) {
        $current_values[] = $value;
        $current_num++;
        $values_changed = TRUE;
      }
    }
  }

  /**
   * Handles the "prepend" operation.
   *
   * @param array $current_values
   *   The current field values.
   * @param array $values
   *   The new values.
   * @param bool $is_unlimited
   *   Whether the field has unlimited cardinality.
   * @param int $cardinality
   *   The field cardinality.
   * @param array $existing
   *   The map of existing indices.
   * @param bool $values_changed
   *   Whether the values have changed.
   */
  private function executePrepend(array &$current_values, array $values, bool $is_unlimited, int $cardinality, array $existing, bool &$values_changed): void {
    $current_num = count($current_values);
    foreach (array_reverse($values, TRUE) as $i => $value) {
      if (!$is_unlimited && $cardinality <= $current_num) {
        break;
      }
      if (!isset($existing[$i])) {
        array_unshift($current_values, $value);
        $current_num++;
        $values_changed = TRUE;
      }
    }
  }

  /**
   * Handles the "remove" operation.
   *
   * @param array $existing
   *   The map of existing indices.
   * @param array $current_values
   *   The current field values.
   * @param bool $values_changed
   *   Whether the values have changed.
   */
  private function executeRemove(array $existing, array &$current_values, bool &$values_changed): void {
    foreach ($existing as $k) {
      unset($current_values[$k]);
      $values_changed = TRUE;
    }
  }

  /**
   * Normalizes the field values.
   *
   * @param array $values
   *   The values to be normalized.
   * @param string $property_name
   *   The name of the property.
   * @param bool $is_property_name_explicit
   *   Whether the property name is explicit.
   *
   * @return array
   *   The normalized values.
   */
  private function normalizeFieldValues(array $values, string $property_name, bool $is_property_name_explicit): array {
    foreach ($values as $i => $value) {
      if ($value instanceof TypedDataInterface) {
        $value = $value->getValue();
        $values[$i] = $value;
      }
      if (is_array($value) && ($is_property_name_explicit || (count($value) === 1))) {
        $value = array_key_exists($property_name, $value) ? $value[$property_name] : reset($value);
      }
      if (is_scalar($value) || is_null($value)) {
        if (!empty($this->configuration['strip_tags'])) {
          $value = preg_replace('/[\t\n\r\0\x0B]/', '', strip_tags((string) $value));
        }
        if (!empty($this->configuration['trim'])) {
          $value = trim((string) $value);
        }
        if ($value === '' || $value === NULL) {
          unset($values[$i]);
        }
        else {
          $values[$i] = [$property_name => $value];
        }
      }
    }
    return $values;
  }

  /**
   * Identifies the existing indices.
   *
   * @param array $current_values
   *   The current field values.
   * @param array $values
   *   The new values. Modified in place: entries may be enriched with existing
   *   item data for 'set' operations.
   * @param string $property_name
   *   The name of the property.
   * @param array $method_settings
   *   The method settings.
   *
   * @return array
   *   The map of existing indices.
   */
  private function identifyExistingIndices(array $current_values, array &$values, string $property_name, array $method_settings): array {
    $existing = [];
    if (!in_array('force_clear', $method_settings, TRUE)) {
      foreach ($current_values as $k => $current_item) {
        if (($i = array_search($current_item, $values, TRUE)) !== FALSE) {
          $existing[$i] = $k;
          continue;
        }

        if (!is_array($current_item)) {
          $current_value = $current_item;
        }
        elseif (array_key_exists($property_name, $current_item)) {
          $current_value = $current_item[$property_name];
        }
        else {
          $current_value = reset($current_item);
        }
        if (is_string($current_value)) {
          // Extra processing is needed for strings, in order to prevent false
          // comparison when dealing with values that are the same but
          // encoded differently.
          $current_value = nl2br(trim($current_value));
        }

        foreach ($values as $i => $value) {
          if (!is_array($value)) {
            $new_value = $value;
          }
          elseif (array_key_exists($property_name, $value)) {
            $new_value = $value[$property_name];
          }
          else {
            $new_value = reset($value);
          }
          if (is_string($new_value)) {
            $new_value = nl2br(trim($new_value));
          }
          if (((is_object($new_value) && $current_value === $new_value) || ($current_value === $new_value)) && !isset($existing[$i]) && !in_array($k, $existing, TRUE)) {
            $existing[$i] = $k;
          }
          if (($i === $k) && is_array($value) && is_array($current_item) && (reset($method_settings) === 'set')) {
            $values[$i] += $current_item;
          }
        }
      }
    }
    return $existing;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE): bool|AccessResultInterface {
    $result = AccessResult::forbidden();
    if (!($object instanceof EntityInterface)) {
      $result->setReason('No entity provided.');
      return $return_as_object ? $result : $result->isAllowed();
    }

    $entity = $object;
    $entity_op = 'update';

    /** @var \Drupal\Core\Access\AccessResultInterface $result */
    $result = $entity->access($entity_op, $account, TRUE);

    $options = ['auto_append' => TRUE, 'access' => 'update'];
    foreach (array_keys($this->getFieldsToUpdate()) as $field) {
      $metadata = [];
      $update_target = $this->getTypedProperty($entity->getTypedData(), $field, $options, $metadata);
      if (!isset($metadata['access']) || (!$update_target && $metadata['access']->isAllowed())) {
        throw new \InvalidArgumentException(sprintf("The provided field %s does not exist as a property path on the %s entity having ID %s.", $field, $entity->getEntityTypeId(), $entity->id()));
      }
      $result = $result->andIf($metadata['access']);
    }

    return $return_as_object ? $result : $result->isAllowed();
  }

}
