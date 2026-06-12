<?php

namespace Drupal\modeler_api;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Form\EnforcedResponseException;
use Drupal\Core\Form\FormAjaxException;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\modeler_api\Form\RuntimePluginForm;
use Drupal\modeler_api\Form\Wrapper;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;

/**
 * Contains a component that's been handled between model owner and modeler.
 */
class Component {

  /**
   * Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface|null
   */
  protected static ?MessengerInterface $messenger;

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface|null
   */
  protected static ?FormBuilderInterface $formBuilder;

  /**
   * Instantiates a new component.
   *
   * @param \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface $owner
   *   The model owner.
   * @param string $id
   *   The ID.
   * @param int $type
   *   The type.
   * @param string $pluginId
   *   The plugin ID.
   * @param string $label
   *   The label.
   * @param array $configuration
   *   The configuration.
   * @param \Drupal\modeler_api\ComponentSuccessor[] $successors
   *   The successors.
   * @param string|null $parentId
   *   The ID of the parent component.
   * @param \Drupal\modeler_api\ComponentColor|null $color
   *   The component color.
   */
  public function __construct(
    protected readonly ModelOwnerInterface $owner,
    protected readonly string $id,
    protected readonly int $type,
    protected readonly string $pluginId = '',
    protected string $label = '',
    protected array $configuration = [],
    protected array $successors = [],
    protected ?string $parentId = NULL,
    protected ?ComponentColor $color = NULL,
  ) {
    assert(in_array($type, Api::AVAILABLE_COMPONENT_TYPES), 'Invalid component type');
  }

  /**
   * Initializes the messenger service.
   *
   * @return \Drupal\Core\Messenger\MessengerInterface
   *   The messenger service.
   */
  protected static function messenger(): MessengerInterface {
    if (!isset(self::$messenger)) {
      self::$messenger = \Drupal::messenger();
    }
    return self::$messenger;
  }

  /**
   * Initializes the form builder service.
   *
   * @return \Drupal\Core\Form\FormBuilderInterface
   *   The form builder service.
   */
  protected static function formBuilder(): FormBuilderInterface {
    if (!isset(self::$formBuilder)) {
      self::$formBuilder = \Drupal::formBuilder();
    }
    return self::$formBuilder;
  }

  /**
   * Get the ID.
   *
   * @return string
   *   The ID.
   */
  public function getId(): string {
    return $this->id;
  }

  /**
   * Get the type.
   *
   * @return int
   *   The type.
   */
  public function getType(): int {
    return $this->type;
  }

  /**
   * Get the plugin ID.
   *
   * @return string
   *   The plugin ID.
   */
  public function getPluginId(): string {
    return $this->pluginId;
  }

  /**
   * Get the label.
   *
   * @return string
   *   The label.
   */
  public function getLabel(): string {
    return $this->label;
  }

  /**
   * Get the configuration.
   *
   * @return array
   *   The configuration.
   */
  public function getConfiguration(): array {
    return $this->configuration;
  }

  /**
   * Get the successors.
   *
   * @return \Drupal\modeler_api\ComponentSuccessor[]
   *   The successors.
   */
  public function getSuccessors(): array {
    return $this->successors;
  }

  /**
   * Get the parent ID.
   *
   * @return string|null
   *   The parent ID or NULL.
   */
  public function getParentId(): ?string {
    return $this->parentId;
  }

  /**
   * Get the component color.
   *
   * @return \Drupal\modeler_api\ComponentColor|null
   *   The component color or NULL.
   */
  public function getColor(): ?ComponentColor {
    return $this->color;
  }

  /**
   * Set the configuration.
   *
   * @param array $configuration
   *   The configuration.
   *
   * @return self
   *   This.
   */
  public function setConfiguration(array $configuration): Component {
    $this->configuration = $configuration;
    return $this;
  }

  /**
   * Set the parent ID.
   *
   * @param string $parentId
   *   The parent ID.
   *
   * @return self
   *   This.
   */
  public function setParentId(string $parentId): Component {
    $this->parentId = $parentId;
    return $this;
  }

  /**
   * Set the successors.
   *
   * @param array $successors
   *   The successors.
   *
   * @return self
   *   This.
   */
  public function setSuccessors(array $successors): Component {
    $this->successors = $successors;
    return $this;
  }

  /**
   * Validate the component.
   *
   * @return string[]
   *   A list of error messages.
   */
  public function validate(): array {
    if ($this->pluginId === '') {
      // Nothing to validate, this component has no plugin.
      return [];
    }
    $configuration = $this->configuration;
    $plugin = $this->owner->ownerComponent($this->type, $this->pluginId, $configuration);
    if ($plugin === NULL) {
      // For some reason we could find the plugin.
      // @todo Log this as an error.
      return [];
    }
    if ($this->owner->skipConfigurationValidation($this->type, $plugin->getPluginId())) {
      return [];
    }
    $typeLabel = $this->owner->ownerComponentId($this->type);
    $replaced_fields = [];
    $errors = [];
    $messenger = self::messenger();

    if ($plugin instanceof ConfigurableInterface) {
      $pluginConfiguration = $plugin->getConfiguration();
      $changed = FALSE;
      foreach ($pluginConfiguration as $key => $value) {
        if (!isset($configuration[$key])) {
          $changed = TRUE;
          if (is_bool($value)) {
            $pluginConfiguration[$key] = FALSE;
          }
          else {
            $pluginConfiguration[$key] = $value;
          }
        }
      }
      if ($changed) {
        $plugin->setConfiguration($pluginConfiguration);
      }
    }
    if ($plugin instanceof PluginFormInterface) {
      // Identify number or email fields and replace them with a valid value if
      // the field is configured with a token. This is important to get those
      // fields through form validation without issues.
      // @todo Add support for nested form fields like e.g. in container/fieldset.
      $form = [];
      $form_state = new FormState();
      foreach ($plugin->buildConfigurationForm($form, $form_state) as $key => $form_field) {
        $value = $configuration[$key] ?? NULL;
        $replacement = NULL;
        $typeReplacement = NULL;
        if (is_array($value)) {
          $typeReplacement = $value;
          $value = implode("\n", $value);
        }
        if ($errorMsg = $this->owner->prepareFormFieldForValidation($value, $replacement, $form_field)) {
          $errors[] = sprintf('%s "%s" (%s): %s', $typeLabel, $this->label, $this->id, $errorMsg);
        }
        if ($typeReplacement !== NULL) {
          $replaced_fields[$key] = $typeReplacement;
        }
        elseif ($replacement !== NULL) {
          $replaced_fields[$key] = $replacement;
        }
        if ($value !== ($configuration[$key] ?? NULL)) {
          $configuration[$key] = $value;
        }
      }
    }

    // Simulate filling and submitting a form for configuring the plugin.
    $form_state = new FormState();
    $form_state->setProgrammed();
    $form_state->setSubmitted();

    if ($plugin instanceof PluginFormInterface) {
      // Build a runtime form for validating the plugin.
      $form_object = new RuntimePluginForm($plugin);
    }
    else {
      $form = $this->owner->buildConfigurationForm($plugin, NULL, FALSE);
      $form_state->addBuildInfo('args', ['embedded' => $form]);
      $form_object = Wrapper::class;
    }

    // Runtime plugin form uses a subform state for the plugin configuration.
    $form_state->setUserInput(['configuration' => $configuration]);
    $form_state->setValues(['configuration' => $configuration]);

    // Keep the currently stored list of messages in mind.
    // The form build will add messages to the messenger, which we want
    // to clear from the runtime.
    $messages_by_type = $messenger->all();

    // Keep the current "has any errors" flag in mind, and reset this flag
    // for the scope of this operation.
    $any_errors = FormState::hasAnyErrors();
    $form_state->clearErrors();

    // Building the form also submits the form, if no errors are there.
    try {
      self::formBuilder()->buildForm($form_object, $form_state);
    }
    catch (EnforcedResponseException | FormAjaxException $e) {
      $errors[] = sprintf('%s "%s" (%s): %s', $typeLabel, $this->label, $this->label, $e->getMessage());
    }

    // Now re-add the previously fetched messages.
    $messenger->deleteAll();
    foreach ($messages_by_type as $messageType => $messages) {
      foreach ($messages as $message) {
        $messenger->addMessage($message, $messageType);
      }
    }

    // Check for errors.
    foreach ($form_state->getErrors() as $error) {
      $errors[] = sprintf('%s "%s" (%s): %s', $typeLabel, $this->label, $this->id, $error);
    }

    if ($any_errors) {
      // Make sure that the form state will have the any errors flag restored.
      (new FormState())->setErrorByName('');
    }

    if (empty($errors)) {
      // Collect the resulting form field values.
      $configuration = ($plugin instanceof ConfigurableInterface ? $plugin->getConfiguration() : []) + $configuration;

      // Restore tokens for numeric configuration fields.
      foreach ($replaced_fields as $key => $original_value) {
        $configuration[$key] = $original_value;
      }

      $this->configuration = $configuration;
    }

    return $errors;
  }

}
