<?php

namespace Drupal\eca_form\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;
use Drupal\Core\Render\Element;
use Drupal\eca\Event\TriggerEvent;

/**
 * Implements form hooks for the ECA Form submodule.
 */
class FormHooks {

  /**
   * Constructs a new FormHooks object.
   */
  public function __construct(
    protected TriggerEvent $triggerEvent,
  ) {}

  /**
   * Implements hook_form_alter().
   */
  #[Hook('form_alter', order: Order::Last)]
  public function formAlter(array &$form, FormStateInterface $form_state): void {
    if (isset($form['#form_id']) && ($form['#form_id'] === 'system_modules_uninstall_confirm_form')) {
      // When this module is being uninstalled via UI, it will lead to a fatal.
      // To avoid this, the module uninstall confirm form is not supported.
      // @see https://www.drupal.org/project/eca/issues/3305797
      return;
    }
    if ($form_state->has('skip_eca')) {
      // When flagged by a component to skip ECA, then skip it.
      return;
    }
    $this->triggerEvent->dispatchFromPlugin('form:form_build', $form, $form_state);
    // Add the handlers on class-level, to avoid expensive and possibly faulty
    // serialization of nested object references during form submissions.
    $form['#process'][] = [static::class, 'process'];
    $form['#after_build'][] = [static::class, 'afterBuild'];
    $form['#validate'][] = [static::class, 'validate'];
    $form['#submit'][] = [static::class, 'submit'];
    $this->addSubmitHandler($form);
  }

  /**
   * Add submit handler to nested elements if necessary.
   *
   * Walks through the element array recursively and adds the extra
   * submit-handler to all elements where necessary.
   *
   * @param array $elements
   *   A render array to walk through.
   */
  protected function addSubmitHandler(array &$elements): void {
    foreach (Element::children($elements) as $key) {
      if (is_array($elements[$key])) {
        // Only add our submit handler, when at least one other submit handler
        // is present for the element. The form submitter service calls
        // form-level submit handlers when no submit handler is specified, i.e.
        // either no #submit array is given at all, or the given array is empty.
        // @see \Drupal\Core\Form\FormSubmitter::executeSubmitHandlers()
        if (!empty($elements[$key]['#submit'])) {
          $submit_handler = [static::class, 'submit'];
          // Make sure our submit handler is added only once.
          if (!in_array($submit_handler, $elements[$key]['#submit'], TRUE)) {
            $elements[$key]['#submit'][] = $submit_handler;
          }
        }
        $this->addSubmitHandler($elements[$key]);
      }
    }
  }

  /**
   * Gets the trigger event service.
   *
   * @return \Drupal\eca\Event\TriggerEvent
   *   The trigger event service.
   */
  public static function triggerEvent(): TriggerEvent {
    return \Drupal::service('eca.trigger_event');
  }

  /**
   * Triggers the event to process a form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form array.
   */
  public static function process(array $form, FormStateInterface $form_state): array {
    if (!$form_state->has('skip_eca')) {
      static::triggerEvent()->dispatchFromPlugin('form:form_process', $form, $form_state);
    }
    return $form;
  }

  /**
   * Triggers the event after form building was completed.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form array.
   */
  public static function afterBuild(array $form, FormStateInterface $form_state): array {
    if (!$form_state->has('skip_eca')) {
      static::triggerEvent()->dispatchFromPlugin('form:form_after_build', $form, $form_state);
    }
    return $form;
  }

  /**
   * Triggers the event to validate a form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function validate(array $form, FormStateInterface $form_state): void {
    if (!$form_state->has('skip_eca')) {
      static::triggerEvent()->dispatchFromPlugin('form:form_validate', $form, $form_state);
    }
  }

  /**
   * Triggers the event to submit a form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function submit(array $form, FormStateInterface $form_state): void {
    if (!$form_state->has('skip_eca')) {
      static::triggerEvent()->dispatchFromPlugin('form:form_submit', $form, $form_state);
    }
  }

}
