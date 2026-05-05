<?php

declare(strict_types=1);

namespace Drupal\custom_field_multivalue_form_element_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\custom_field\Element\MultiValue;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form to test the multivalue form element.
 *
 * State can be used to pass the default values to use and to retrieve the
 * submitted values.
 */
class ElementTestForm extends FormBase {

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructs an ElementTestForm object.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(StateInterface $state) {
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('state'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'multivalue_form_element_element_test_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $default_values = $this->state->get('multivalue_form_element_test_default_values', []);

    // An element with a single child and unlimited cardinality.
    $form['foo'] = [
      '#type' => 'custom_field_multivalue',
      '#cardinality' => MultiValue::CARDINALITY_UNLIMITED,
      '#title' => $this->t('Foo'),
      'text' => [
        '#type' => 'textfield',
        '#title' => $this->t('Text'),
      ],
    ];

    // Add the default value for foo only if passed. In this way we can cover
    // the scenario when no #default_value key is passed.
    if (isset($default_values['foo'])) {
      $form['foo']['#default_value'] = $default_values['foo'];
    }

    // An element with a single child and limited cardinality.
    $form['bar'] = [
      '#type' => 'custom_field_multivalue',
      '#cardinality' => 3,
      '#title' => $this->t('Bar'),
      'number' => [
        '#type' => 'number',
        '#title' => $this->t('Number'),
      ],
      '#default_value' => $default_values['bar'] ?? [],
    ];

    // An element with two children.
    $form['complex'] = [
      '#type' => 'custom_field_multivalue',
      '#cardinality' => MultiValue::CARDINALITY_UNLIMITED,
      '#title' => $this->t('Complex'),
      '#add_more_label' => $this->t('Add more complexity'),
      'text' => [
        '#type' => 'textfield',
        '#title' => $this->t('Text'),
      ],
      'number' => [
        '#type' => 'number',
        '#title' => $this->t('Number'),
      ],
      '#default_value' => $default_values['complex'] ?? [],
    ];

    // A nested element, used to test the generation of the button name and
    // AJAX wrapper.
    $form['nested'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      'inner' => [
        '#type' => 'container',
        'foo' => [
          '#type' => 'custom_field_multivalue',
          '#cardinality' => MultiValue::CARDINALITY_UNLIMITED,
          '#title' => $this->t('Inner foo'),
          'bar' => [
            '#type' => 'checkboxes',
            '#title' => $this->t('Values'),
            '#options' => [
              'a' => $this->t('Value A'),
              'b' => $this->t('Value B'),
            ],
          ],
        ],
      ],
    ];

    // A nested element with a deep structure.
    $form['nested_deep'] = [
      '#type' => 'custom_field_multivalue',
      '#cardinality' => MultiValue::CARDINALITY_UNLIMITED,
      '#title' => $this->t('Nested deep'),
      '#default_value' => $default_values['nested_deep'] ?? [],
      'enabled' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Enabled'),
      ],
      'content'  => [
        '#type' => 'fieldset',
        '#title' => $this->t('Content 1'),
        'title' => [
          '#type' => 'textfield',
          '#title' => $this->t('Title'),
        ],
        'body' => [
          '#type' => 'textarea',
          '#title' => $this->t('Body'),
        ],
        'tags' => [
          '#type' => 'custom_field_multivalue',
          '#cardinality' => MultiValue::CARDINALITY_UNLIMITED,
          '#add_more_label' => $this->t('Add level 1 tag'),
          '#title' => $this->t('Tags'),
          'tag' => [
            '#type' => 'textfield',
            '#title' => $this->t('Tag'),
          ],
        ],
        'content_2'  => [
          '#type' => 'fieldset',
          '#title' => $this->t('Content 2'),
          'title' => [
            '#type' => 'textfield',
            '#title' => $this->t('Title'),
          ],
          'body' => [
            '#type' => 'textarea',
            '#title' => $this->t('Body'),
          ],
          'tags' => [
            '#type' => 'custom_field_multivalue',
            '#cardinality' => MultiValue::CARDINALITY_UNLIMITED,
            '#add_more_label' => $this->t('Add level 2 tag'),
            '#title' => $this->t('Tags'),
            'tag' => [
              '#type' => 'textfield',
              '#title' => $this->t('Tag'),
            ],
          ],
          'content_3'  => [
            '#type' => 'fieldset',
            '#title' => $this->t('Content 3'),
            'title' => [
              '#type' => 'textfield',
              '#title' => $this->t('Title'),
            ],
            'body' => [
              '#type' => 'textarea',
              '#title' => $this->t('Body'),
            ],
            'tags' => [
              '#type' => 'custom_field_multivalue',
              '#cardinality' => MultiValue::CARDINALITY_UNLIMITED,
              '#add_more_label' => $this->t('Add level 3 tag'),
              '#title' => $this->t('Tags'),
              'tag' => [
                '#type' => 'textfield',
                '#title' => $this->t('Tag'),
              ],
            ],
          ],
        ],
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->state->set('multivalue_form_element_test_submitted_values', $form_state->getValues());
  }

}
