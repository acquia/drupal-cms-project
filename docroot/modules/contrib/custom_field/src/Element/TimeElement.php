<?php

declare(strict_types=1);

namespace Drupal\custom_field\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\FormElementBase;
use Drupal\custom_field\Time;

/**
 * Provides a time field form element.
 *
 * Usage example:
 *
 * @code
 * $form['time'] = [
 *   '#type' => 'time_cf',
 *   '#title' => $this->t('Time'),
 *   '#required' => TRUE,
 * ];
 * @endcode
 *
 * A stripped copy of
 *  https://git.drupalcode.org/project/time_field/-/blob/2.x/src/Element/TimeElement.php.
 */
#[FormElement('time_cf')]
class TimeElement extends FormElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    $class = get_class($this);
    return [
      '#show_seconds' => FALSE,
      '#input' => TRUE,
      '#process' => [
        [$class, 'processAjaxForm'],
      ],
      '#pre_render' => [
        [$class, 'preRenderTime'],
      ],
      '#theme' => 'input__time',
      '#theme_wrappers' => ['form_element'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state): ?int {

    if ($input === FALSE && !is_null($element['#default_value'])) {
      $input = $element['#default_value'];
    }

    if (is_string($input) && $input !== '') {
      return Time::createFromHtml5Format($input)->getTimestamp();
    }

    return NULL;
  }

  /**
   * Prepares a #type 'time' render element for input.html.twig.
   *
   * @param array<string, mixed> $element
   *   An associative array containing the properties of the element.
   *   Properties used: #title, #value, #description, #size, #maxlength,
   *   #placeholder, #required, #attributes.
   *
   * @return array<string, mixed>
   *   The $element with prepared variables ready for input.html.twig.
   */
  public static function preRenderTime(array $element): array {
    $element['#attributes']['type'] = 'time';
    $element['#attributes']['class'] = ['form-time'];

    if (is_int($element['#value'])) {
      $element['#value'] = Time::createFromTimestamp($element['#value'])
        ->formatForWidget($element['#show_seconds']);
    }

    Element::setAttributes($element, [
      'id',
      'name',
      'value',
      'size',
      'maxlength',
      'placeholder',
    ]);
    static::setAttributes($element, ['form-text']);

    return $element;
  }

}
