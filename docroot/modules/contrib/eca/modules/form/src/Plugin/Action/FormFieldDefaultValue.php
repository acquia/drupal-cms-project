<?php

namespace Drupal\eca_form\Plugin\Action;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Datetime\Element\DateElementBase;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Button;
use Drupal\Core\Render\Element\Checkboxes;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Set default value of a form field.
 */
#[Action(
  id: 'eca_form_field_default_value',
  label: new TranslatableMarkup('Form field: set default value'),
  type: 'form',
)]
#[EcaAction(
  description: new TranslatableMarkup('Prepopulates a default value in the form.'),
  version_introduced: '1.0.0',
)]
class FormFieldDefaultValue extends FormFieldActionBase {

  /**
   * The element info manager.
   *
   * @var \Drupal\Core\Render\ElementInfoManagerInterface
   */
  protected ElementInfoManagerInterface $elementInfoManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->elementInfoManager = $container->get('plugin.manager.element_info');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(): void {
    if ($element = &$this->getTargetElement()) {
      $element = &$this->jumpToFirstFieldChild($element);
      $value = $this->configuration['value'];
      $default_value_key = '#default_value';

      // Normalizing the element type.
      switch ($element['#type'] ?? NULL) {

        case 'date':
        case 'datelist':
        case 'datetime':
          $type = 'date';
          break;

        case 'entity_autocomplete':
          $type = 'entity_autocomplete';
          break;

        case 'checkboxes':
          $type = 'checkboxes';
          break;

        case 'button':
        case 'hidden':
        case 'submit':
          $type = 'button';
          break;

        default:
          $type = $element['#type'] ?? NULL;
          if ($type !== NULL) {
            // Determine if the given type is a sub-type of one of the core
            // types.
            $definition = $this->elementInfoManager->getDefinition($type) ?? [];
            $plugin_class = $definition['class'] ?? NULL;
            if ($plugin_class !== NULL) {
              if (is_subclass_of($plugin_class, DateElementBase::class)) {
                $type = 'date';
              }
              elseif (is_subclass_of($plugin_class, EntityAutocomplete::class)) {
                $type = 'entity_autocomplete';
              }
              elseif (is_subclass_of($plugin_class, Checkboxes::class)) {
                $type = 'checkboxes';
              }
              elseif (is_subclass_of($plugin_class, Button::class)) {
                $type = 'button';
              }
            }
          }

      }

      switch ($type) {

        case 'date':
          $value = (string) $this->tokenService->replaceClear($value);
          $this->filterFormFieldValue($value);
          if (mb_substr($value, 0, 1) === '@') {
            $value = mb_substr($value, 1);
          }
          $value = ctype_digit($value) ? new DrupalDateTime("@{$value}") : new DrupalDateTime($value, new \DateTimeZone('UTC'));
          break;

        case 'entity_autocomplete':
          $value = $this->tokenService->getOrReplace($value);
          if ($value instanceof EntityReferenceFieldItemListInterface) {
            $value = $value->referencedEntities();
          }
          elseif ($value instanceof EntityReferenceItem) {
            $value = $value->entity ?? NULL;
          }
          elseif ($value instanceof TypedDataInterface) {
            $value = $value->getValue();
          }
          if (is_object($value) && !($value instanceof EntityInterface) && method_exists($value, '__toString')) {
            $value = (string) $value;
          }
          $entities = [];
          $values = is_array($value) ? $value : [$value];
          foreach ($values as $value) {
            if ($value instanceof EntityInterface) {
              $entities[] = $value;
            }
            elseif (is_scalar($value) && isset($element['#target_type']) && ($entity = $this->entityTypeManager->getStorage($element['#target_type'])->load($value))) {
              $entities[] = $entity;
            }
          }
          $value = array_filter($entities, function ($entity) {
            /**
             * @var \Drupal\Core\Entity\EntityInterface $entity
             */
            return $entity->access('view', $this->currentUser);
          });
          if (empty($value)) {
            $value = NULL;
          }
          elseif ((($element['#tags'] ?? NULL) !== TRUE) || (count($value) === 1)) {
            $value = reset($value);
          }
          break;

        case 'checkboxes':
          $value = $this->tokenService->getOrReplace($value);
          if (is_scalar($value) || $value instanceof MarkupInterface) {
            $value = DataTransferObject::buildArrayFromUserInput($value);
          }
          elseif ($value instanceof DataTransferObject) {
            $value = $value->toArray();
          }
          elseif (!is_array($value)) {
            $value = (array) $value;
          }
          break;

        case 'button':
          $default_value_key = '#value';

        default:
          $value = (string) $this->tokenService->replaceClear($value);
          $this->filterFormFieldValue($value);

      }

      $element[$default_value_key] = $value;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'value' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Value'),
      '#description' => $this->t('The default value to prepopulate.'),
      '#default_value' => $this->configuration['value'],
      '#weight' => -49,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['value'] = $form_state->getValue('value');
    parent::submitConfigurationForm($form, $form_state);
  }

}
