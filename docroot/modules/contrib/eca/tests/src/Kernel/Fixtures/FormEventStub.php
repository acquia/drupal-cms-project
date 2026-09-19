<?php

namespace Drupal\Tests\eca\Kernel\Fixtures;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Event\FormEventInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * A minimal form event, for testing the form execution subscriber.
 *
 * BeforeInitialExecutionEvent type hints its system event against the Symfony
 * Event class, so a plain FormEventInterface test double cannot be passed to
 * it. PHPUnit cannot generate a double that is both a subclass of Event and an
 * implementation of FormEventInterface, hence this hand-written stub.
 */
class FormEventStub extends Event implements FormEventInterface {

  /**
   * The form array.
   *
   * @var array|null
   */
  protected ?array $form = NULL;

  /**
   * The form state.
   *
   * @var \Drupal\Core\Form\FormStateInterface
   */
  protected FormStateInterface $formState;

  /**
   * Constructs a FormEventStub object.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state to hand out.
   */
  public function __construct(FormStateInterface $form_state) {
    $this->formState = $form_state;
  }

  /**
   * {@inheritdoc}
   */
  public function &getForm(): ?array {
    return $this->form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormState(): FormStateInterface {
    return $this->formState;
  }

}
