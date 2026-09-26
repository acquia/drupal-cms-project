<?php

namespace Drupal\toastify\Form;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Toastify settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->moduleHandler = $container->get('module_handler');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'toastify_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['toastify.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('toastify.settings');
    $colorElement = $this->moduleHandler->moduleExists('jquery_colorpicker')
      ? 'jquery_colorpicker'
      : 'color';

    $form['status'] = [
      '#type' => 'details',
      '#title' => $this->t('Status'),
      '#open' => TRUE,
    ];

    $form['warning'] = [
      '#type' => 'details',
      '#title' => $this->t('Warning'),
    ];

    $form['error'] = [
      '#type' => 'details',
      '#title' => $this->t('Error'),
    ];

    foreach (['status', 'warning', 'error'] as $type) {
      $form[$type][$type . '_duration'] = [
        '#type' => 'number',
        '#title' => $this->t('Duration'),
        '#default_value' => $config->get($type . '.duration'),
        '#required' => TRUE,
        '#min' => 0,
      ];

      $form[$type][$type . '_gravity'] = [
        '#type' => 'select',
        '#title' => $this->t('Gravity'),
        '#default_value' => $config->get($type . '.gravity'),
        '#options' => [
          'top' => $this->t('Top'),
          'bottom' => $this->t('Bottom'),
        ],
      ];

      $form[$type][$type . '_position'] = [
        '#type' => 'select',
        '#title' => t('Position'),
        '#default_value' => $config->get($type . '.position'),
        '#options' => [
          'left' => t('Left'),
          'right' => t('Right'),
          'center' => t('Center'),
        ],
      ];

      $form[$type][$type . '_offsetX'] = [
        '#type' => 'number',
        '#title' => $this->t('Offset X'),
        '#default_value' => $config->get($type . '.offsetX') ?? 0,
        '#description' => $this->t('Offset from the left or right side of the screen.'),
      ];

      $form[$type][$type . '_offsetY'] = [
        '#type' => 'number',
        '#title' => $this->t('Offset Y'),
        '#default_value' => $config->get($type . '.offsetY') ?? 0,
        '#description' => $this->t('Offset from the top or bottom of the screen.'),
      ];

      if (!_toastify_is_gin_theme_active()) {
        $form[$type][$type . '_color'] = [
          '#type' => $colorElement,
          '#title' => $this->t('Color'),
          '#default_value' => $config->get($type . '.color'),
        ];

        $form[$type][$type . '_color2'] = [
          '#type' => $colorElement,
          '#title' => $this->t('Color 2'),
          '#default_value' => $config->get($type . '.color2'),
        ];

        $form[$type][$type . '_colorProgressBar'] = [
          '#type' => $colorElement,
          '#title' => $this->t('Color (progress bar)'),
          '#default_value' => $config->get($type . '.colorProgressBar'),
        ];

        $form[$type][$type . '_direction'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Gradient direction'),
          '#default_value' => $config->get($type . '.direction'),
        ];
      }

      $form[$type][$type . '_close'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Close button'),
        '#default_value' => $config->get($type . '.close'),
      ];
    }
    $form['enable_for'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Enable for'),
      '#tree' => TRUE,
    ];
    $form['enable_for']['admin_theme'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Admin theme'),
      '#default_value' => $config->get('enable_for.admin_theme'),
    ];
    $form['enable_for']['frontend_theme'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Frontend theme'),
      '#default_value' => $config->get('enable_for.frontend_theme'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('toastify.settings');
    foreach (['status', 'warning', 'error'] as $type) {
      $config->set($type . '.duration', $form_state->getValue($type . '_duration'));
      $config->set($type . '.gravity', $form_state->getValue($type . '_gravity'));
      $config->set($type . '.position', $form_state->getValue($type . '_position'));
      $config->set($type . '.close', boolval($form_state->getValue($type . '_close')));
      $config->set($type . '.offsetX', $form_state->getValue($type . '_offsetX'));
      $config->set($type . '.offsetY', $form_state->getValue($type . '_offsetY'));

      if (!_toastify_is_gin_theme_active()) {
        $config->set($type . '.color', $form_state->getValue($type . '_color'));
        $config->set($type . '.color2', $form_state->getValue($type . '_color2'));
        $config->set($type . '.colorProgressBar', $form_state->getValue($type . '_colorProgressBar'));
        $config->set($type . '.direction', Xss::filter($form_state->getValue($type . '_direction')));
      }
    }

    // Save the "Enable for" settings.
    $config->set('enable_for.admin_theme', (bool) $form_state->getValue(['enable_for', 'admin_theme']));
    $config->set('enable_for.frontend_theme', (bool) $form_state->getValue(['enable_for', 'frontend_theme']));

    $config->save();

    $this->messenger()->addError('This is an example error message.');
    $this->messenger()->addWarning('This is an example warning message.');
    $this->messenger()->addStatus('This is an example status message.');

    parent::submitForm($form, $form_state);
  }

}
